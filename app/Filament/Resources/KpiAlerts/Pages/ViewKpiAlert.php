<?php

namespace App\Filament\Resources\KpiAlerts\Pages;

use App\Filament\Resources\KpiAlerts\KpiAlertResource;
use Filament\Resources\Pages\ViewRecord;

class ViewKpiAlert extends ViewRecord
{
    protected static string $resource = KpiAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
