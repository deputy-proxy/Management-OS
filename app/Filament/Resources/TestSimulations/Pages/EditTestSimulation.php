<?php

namespace App\Filament\Resources\TestSimulations\Pages;

use App\Filament\Resources\TestSimulations\TestSimulationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTestSimulation extends EditRecord
{
    protected static string $resource = TestSimulationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
