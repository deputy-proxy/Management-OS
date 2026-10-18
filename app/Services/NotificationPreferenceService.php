<?php

namespace App\Services;

use App\Jobs\SendModelEventEmail;
use App\Mail\ModelEventMail;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Hexters\HexaLite\Helpers\HexaHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class NotificationPreferenceService
{
    private const CHANNELS = ['in_app', 'email'];

    /** @return array<int, array{event_key: string, label: string, model: string, event: string, channels: array<int, string>}> */
    public function catalogFor(User $user): array
    {
        $events = [];
        $hasVisibleResource = false;

        foreach ($this->resources() as $resource) {
            $details = $this->resourceDetails($resource);

            if (! $details || ! $this->userCan($user, $details['index_permission'])) {
                continue;
            }

            $hasVisibleResource = true;
            foreach (['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'] as $event => $label) {
                $events[] = [
                    'event_key' => "model.{$details['key']}.{$event}",
                    'label' => "{$details['label']} {$label}",
                    'model' => $details['model'],
                    'event' => $event,
                    'channels' => self::CHANNELS,
                ];
            }
        }

        if ($hasVisibleResource) {
            $events[] = [
                'event_key' => 'comment.created',
                'label' => 'New comment on an accessible record',
                'model' => 'Comments',
                'event' => 'created',
                'channels' => self::CHANNELS,
            ];
        }

        usort($events, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $events;
    }

    public function allows(User $user, string $eventKey, string $channel = 'email', ?Model $context = null): bool
    {
        if (! in_array($channel, self::CHANNELS, true) || ! $this->isAuthorizedEvent($user, $eventKey, $context)) {
            return false;
        }

        return NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->where('enabled', true)
            ->exists();
    }

    /** @param array<string, array<string, bool>> $preferences */
    public function savePreferences(User $user, array $preferences): void
    {
        $catalog = collect($this->catalogFor($user))->keyBy('event_key');

        foreach ($preferences as $eventKey => $channels) {
            if (! is_string($eventKey) || ! $catalog->has($eventKey) || ! is_array($channels)) {
                throw ValidationException::withMessages(['preferences' => 'One or more notification events are no longer available for your role. Reload the settings and try again.']);
            }

            foreach ($channels as $channel => $enabled) {
                if (! in_array($channel, self::CHANNELS, true) || ! is_bool($enabled)) {
                    throw ValidationException::withMessages(['preferences' => 'The notification preference payload is invalid.']);
                }
            }
        }

        foreach ($catalog as $eventKey => $event) {
            foreach ($event['channels'] as $channel) {
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->getKey(), 'event_key' => $eventKey, 'channel' => $channel],
                    ['enabled' => (bool) ($preferences[$eventKey][$channel] ?? false)],
                );
            }
        }
    }

    public function dispatchModelEvent(Model $model, string $event): void
    {
        $details = $this->detailsForModel($model);

        if (! $details || ! in_array($event, ['created', 'updated', 'deleted'], true)) {
            return;
        }

        $eventKey = "model.{$details['key']}.{$event}";
        $timestamp = optional($model->getAttribute('updated_at') ?? $model->getAttribute('deleted_at'))->toISOString() ?? (string) $model->getKey();
        $changeFingerprint = hash('sha256', json_encode($model->getChanges(), JSON_THROW_ON_ERROR));
        $eventId = implode(':', [$model::class, $model->getKey(), $event, $timestamp, substr($changeFingerprint, 0, 16)]);
        $recordTitle = $this->recordTitle($model);
        $payload = [
            'subject' => "{$details['label']} {$event}",
            'record_title' => $recordTitle,
            'message' => "{$details['label']} '{$recordTitle}' was {$event}.",
            'url' => url('/admin'),
        ];

        DB::afterCommit(function () use ($eventKey, $eventId, $payload): void {
            User::query()
                ->whereHas('notificationPreferences', fn ($query) => $query->where('event_key', $eventKey)->where('enabled', true))
                ->with('roles')
                ->each(function (User $user) use ($eventKey, $eventId, $payload): void {
                    foreach (self::CHANNELS as $channel) {
                        if (! $this->allows($user, $eventKey, $channel)) {
                            continue;
                        }

                        $delivery = NotificationDelivery::query()->firstOrCreate([
                            'user_id' => $user->getKey(),
                            'event_key' => $eventKey,
                            'event_id' => $eventId,
                            'channel' => $channel,
                        ], ['status' => 'pending', 'payload' => $payload]);

                        if ($delivery->status === 'sent') {
                            continue;
                        }

                        if ($channel === 'in_app') {
                            $this->deliverInApp($user, $eventKey, $eventId, $payload);
                        } else {
                            $delivery->update(['status' => 'pending', 'payload' => $payload]);
                            SendModelEventEmail::dispatch($delivery->getKey())->afterCommit();
                        }
                    }
                });
        });
    }

    public function deliverInApp(User $user, string $eventKey, string $eventId, array $payload, ?Model $context = null): void
    {
        if (! $this->allows($user, $eventKey, 'in_app', $context)) {
            return;
        }

        $delivery = NotificationDelivery::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'event_key' => $eventKey,
            'event_id' => $eventId,
            'channel' => 'in_app',
        ], ['status' => 'pending', 'payload' => $payload]);

        if ($delivery->status === 'sent') {
            return;
        }

        Notification::make()
            ->title((string) ($payload['subject'] ?? 'Notification'))
            ->body((string) ($payload['message'] ?? $payload['comment_body'] ?? ''))
            ->sendToDatabase($user)
            ->broadcast($user);

        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }

    public function sendModelEventEmail(NotificationDelivery $delivery): void
    {
        if ($delivery->status === 'sent') {
            return;
        }

        $user = User::query()->find($delivery->user_id);
        if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL) || ! $this->allows($user, $delivery->event_key, 'email')) {
            $delivery->update(['status' => 'skipped', 'last_error' => null]);

            return;
        }

        $payload = $delivery->payload;
        if (! is_array($payload) || empty($payload['subject']) || empty($payload['message']) || empty($payload['url'])) {
            throw new \RuntimeException('Model notification email payload is incomplete.');
        }

        Mail::to($user->email)->send(new ModelEventMail($payload));
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }

    private function isAuthorizedEvent(User $user, string $eventKey, ?Model $context): bool
    {
        if ($eventKey === 'comment.created') {
            if ($context) {
                return $this->userCanAccessModel($user, $context);
            }

            return collect($this->catalogFor($user))->contains('event_key', 'comment.created');
        }

        $event = collect($this->catalogFor($user))->firstWhere('event_key', $eventKey);
        if (! $event) {
            return false;
        }

        if (! $context) {
            return true;
        }

        $details = $this->detailsForModel($context);

        return $details !== null && str_starts_with($eventKey, 'model.'.$details['key'].'.');
    }

    private function userCanAccessModel(User $user, Model $model): bool
    {
        $details = $this->detailsForModel($model);

        return $details !== null && $this->userCan($user, $details['index_permission']);
    }

    private function userCan(User $user, string $permission): bool
    {
        return (new HexaHelper)->user($user)->can($permission);
    }

    /** @return array<int, class-string> */
    private function resources(): array
    {
        try {
            return Filament::getResources();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{resource: class-string, model: class-string<Model>, key: string, label: string, index_permission: string}|null */
    private function resourceDetails(string $resource): ?array
    {
        if (! method_exists($resource, 'getModel')) {
            return null;
        }

        $model = $resource::getModel();
        if (! is_string($model) || ! is_subclass_of($model, Model::class) || ! str_starts_with($model, 'App\\Models\\')) {
            return null;
        }

        if (! method_exists($resource, 'defineGates')) {
            return null;
        }

        $gates = app($resource)->defineGates();
        $indexPermission = collect(array_keys($gates))->first(fn (string $gate): bool => str_ends_with($gate, '.index'));
        if (! $indexPermission) {
            return null;
        }

        $key = substr($indexPermission, 0, -strlen('.index'));
        $label = method_exists($resource, 'getModelLabel') ? (string) $resource::getModelLabel() : class_basename($model);

        return ['resource' => $resource, 'model' => $model, 'key' => $key, 'label' => $label, 'index_permission' => $indexPermission];
    }

    /** @return array{resource: class-string, model: class-string<Model>, key: string, label: string, index_permission: string}|null */
    private function detailsForModel(Model $model): ?array
    {
        foreach ($this->resources() as $resource) {
            $details = $this->resourceDetails($resource);
            if ($details && $details['model'] === $model::class) {
                return $details;
            }
        }

        return null;
    }

    private function recordTitle(Model $model): string
    {
        foreach (['title', 'name', 'full_name', 'email', 'code'] as $attribute) {
            $value = $model->getAttribute($attribute);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr((string) $value, 0, 180);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
