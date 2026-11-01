<?php

return [
    // Delivery remains disabled until an owner approves thresholds and recipients.
    'delivery_enabled' => (bool) env('KPI_ALERT_DELIVERY_ENABLED', false),
    'default_cooldown_minutes' => (int) env('KPI_ALERT_DEFAULT_COOLDOWN_MINUTES', 60),
    'maximum_freshness_seconds' => (int) env('KPI_ALERT_MAXIMUM_FRESHNESS_SECONDS', 86400),
];
