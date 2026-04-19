<?php

namespace App\Filament\Resources\ProductCosts\Pages;

use App\Filament\Resources\ProductCosts\ProductCostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductCosts extends ListRecords
{
    protected static string $resource = ProductCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

