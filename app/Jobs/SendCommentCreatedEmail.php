<?php

namespace App\Jobs;

use App\Mail\CommentCreatedMail;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendCommentCreatedEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(NotificationPreferenceService $preferences): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);

        if (! $delivery || $delivery->status === 'sent') {
            return;
        }

        $user = User::query()->find($delivery->user_id);

        if (! $user || ! $preferences->allows($user, $delivery->event_key, $delivery->channel)) {
            $delivery->update(['status' => 'skipped', 'last_error' => null]);

            return;
        }

        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Notification recipient has no valid email address.');
        }

        $details = $delivery->payload;

        if (! is_array($details) || empty($details['subject']) || empty($details['record_title']) || ! array_key_exists('comment_body', $details) || empty($details['url'])) {
            throw new RuntimeException('Notification delivery payload is incomplete.');
        }

        Mail::to($user->email)->send(new CommentCreatedMail($details));

        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }

    public function failed(Throwable $exception): void
    {
        NotificationDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
        ]);

        Log::error('Comment notification email delivery failed.', [
            'delivery_id' => $this->deliveryId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
