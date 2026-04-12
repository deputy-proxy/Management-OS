<?php

namespace App\Filament\Resources\PlacementTests\Pages;

use App\Filament\Resources\PlacementTests\PlacementTestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPlacementTest extends ViewRecord
{
    protected static string $resource = PlacementTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

