<?php

namespace App\Filament\Resources\ProductRevenues\Pages;

use App\Filament\Resources\ProductRevenues\ProductRevenueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductRevenue extends EditRecord
{
    protected static string $resource = ProductRevenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

