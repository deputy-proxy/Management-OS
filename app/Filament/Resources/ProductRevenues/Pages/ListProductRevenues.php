<?php

namespace App\Filament\Resources\ProductRevenues\Pages;

use App\Filament\Resources\ProductRevenues\ProductRevenueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductRevenues extends ListRecords
{
    protected static string $resource = ProductRevenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

