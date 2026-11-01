<?php

namespace App\Services;

use App\Contracts\KpiAlertDeliveryAdapter;
use App\Models\KpiAlert;
use App\Models\User;
use Filament\Notifications\Notification;

class DatabaseKpiAlertDeliveryAdapter implements KpiAlertDeliveryAdapter
{
    public function deliver(User $recipient, KpiAlert $alert, string $event): void
    {
        $label = $alert->definition->label ?? 'KPI';
        $severity = ucfirst($alert->severity);
        $message = $event === 'raised'
            ? "{$severity} KPI alert: {$label} observed {$alert->observed_value} against threshold {$alert->trigger_threshold}."
            : "KPI alert for {$label} changed to {$alert->status}.";

        Notification::make()
            ->title($event === 'raised' ? 'KPI threshold alert' : 'KPI alert updated')
            ->body($message)
            ->sendToDatabase($recipient);
    }
}
