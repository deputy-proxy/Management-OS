<?php

namespace App\Filament\Resources\WorkerEngagements\Pages;

use App\Filament\Resources\WorkerEngagements\WorkerEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkerEngagement extends ViewRecord
{
    protected static string $resource = WorkerEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
