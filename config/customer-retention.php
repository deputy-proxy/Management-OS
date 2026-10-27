<?php

return [
    // Cohort reporting starts only when lifecycle events can be captured consistently.
    // Do not backfill this date or infer churn from contract dates, inactivity, or deletion.
    'tracking_start_at' => env('CUSTOMER_RETENTION_TRACKING_START_AT', '2026-10-27 00:00:00'),
    'timezone' => 'Europe/Bucharest',
];
