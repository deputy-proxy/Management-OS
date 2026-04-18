<?php

namespace App\Filament\Resources\Camps\Pages;

use App\Filament\Resources\Camps\CampResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCamps extends ListRecords
{
    protected static string $resource = CampResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

