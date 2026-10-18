<?php

namespace App\Providers;

// Ensure this matches your package
use App\Services\NotificationPreferenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ModelNotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (['created', 'updated', 'deleted'] as $modelEvent) {
            Event::listen('eloquent.'.$modelEvent.': *', function (string $eventName, array $payload) use ($modelEvent): void {
                $model = $payload[0] ?? null;
                if ($model instanceof Model) {
                    app(NotificationPreferenceService::class)->dispatchModelEvent($model, $modelEvent);
                }
            });
        }
    }
}
