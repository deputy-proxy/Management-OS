<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendModelEventEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId) {}

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

        $preferences->sendModelEventEmail($delivery);
    }

    public function failed(Throwable $exception): void
    {
        NotificationDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
        Log::error('Model notification email delivery failed.', ['delivery_id' => $this->deliveryId, 'exception' => $exception->getMessage()]);
    }
}
