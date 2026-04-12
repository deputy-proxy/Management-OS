<?php

namespace App\Filament\Resources\GroupSessions\Pages;

use App\Filament\Resources\GroupSessions\GroupSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupSessions extends ListRecords
{
    protected static string $resource = GroupSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

