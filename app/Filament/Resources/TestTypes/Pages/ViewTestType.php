<?php

namespace App\Filament\Resources\TestTypes\Pages;

use App\Filament\Resources\TestTypes\TestTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTestType extends ViewRecord
{
    protected static string $resource = TestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

