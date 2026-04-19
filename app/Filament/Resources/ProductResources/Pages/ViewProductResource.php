<?php

namespace App\Filament\Resources\ProductResources\Pages;

use App\Filament\Resources\ProductResources\ProductResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductResource extends ViewRecord
{
    protected static string $resource = ProductResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

