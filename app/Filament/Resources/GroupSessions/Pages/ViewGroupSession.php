<?php

namespace App\Filament\Resources\GroupSessions\Pages;

use App\Filament\Resources\GroupSessions\GroupSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGroupSession extends ViewRecord
{
    protected static string $resource = GroupSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

