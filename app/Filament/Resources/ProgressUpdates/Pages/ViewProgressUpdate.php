<?php

namespace App\Filament\Resources\ProgressUpdates\Pages;

use App\Filament\Resources\ProgressUpdates\ProgressUpdateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProgressUpdate extends ViewRecord
{
    protected static string $resource = ProgressUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

