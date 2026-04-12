<?php

namespace App\Filament\Resources\ProgressUpdates\Pages;

use App\Filament\Resources\ProgressUpdates\ProgressUpdateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProgressUpdate extends EditRecord
{
    protected static string $resource = ProgressUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

