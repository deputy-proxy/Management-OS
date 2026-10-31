<?php

namespace App\Filament\Resources\WorkerEngagements\Pages;

use App\Filament\Resources\WorkerEngagements\WorkerEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWorkerEngagement extends EditRecord
{
    protected static string $resource = WorkerEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
