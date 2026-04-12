<?php

namespace App\Filament\Resources\PlacementTests\Pages;

use App\Filament\Resources\PlacementTests\PlacementTestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlacementTests extends ListRecords
{
    protected static string $resource = PlacementTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

