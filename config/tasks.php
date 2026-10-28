<?php

return [
    // Operational default only. Business owners can tune this after review.
    'escalation_overdue_days' => (int) env('TASK_ESCALATION_OVERDUE_DAYS', 1),
];
