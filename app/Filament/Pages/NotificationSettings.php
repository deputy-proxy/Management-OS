<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\NotificationPreferenceService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class NotificationSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Notification Settings';

    protected string $view = 'filament.pages.notification-settings';

    /** @var array<int, array{event_key: string, label: string, model: string, event: string, channels: array<int, string>}> */
    public array $events = [];

    /** @var array<int, array{in_app: bool, email: bool}> */
    public array $preferences = [];

    public function mount(): void
    {
        $this->loadPreferences();
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function save(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $catalog = app(NotificationPreferenceService::class)->catalogFor($user);
        $preferences = [];

        foreach ($catalog as $index => $event) {
            $state = $this->preferences[$index] ?? [];
            $preferences[$event['event_key']] = [
                'in_app' => (bool) ($state['in_app'] ?? false),
                'email' => (bool) ($state['email'] ?? false),
            ];
        }

        app(NotificationPreferenceService::class)->savePreferences($user, $preferences);
        $this->loadPreferences();

        Notification::make()->title('Notification preferences saved')->success()->send();
    }

    private function loadPreferences(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->events = app(NotificationPreferenceService::class)->catalogFor($user);
        $saved = $user->notificationPreferences()->get()->keyBy(fn ($preference) => $preference->event_key.':'.$preference->channel);
        $this->preferences = [];

        foreach ($this->events as $index => $event) {
            $this->preferences[$index] = [
                'in_app' => (bool) ($saved->get($event['event_key'].':in_app')?->enabled ?? false),
                'email' => (bool) ($saved->get($event['event_key'].':email')?->enabled ?? false),
            ];
        }
    }
}
