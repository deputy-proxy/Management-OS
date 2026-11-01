<?php

namespace App\Contracts;

use App\Models\KpiAlert;
use App\Models\User;

interface KpiAlertDeliveryAdapter
{
    public function deliver(User $recipient, KpiAlert $alert, string $event): void;
}
