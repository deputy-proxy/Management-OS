<?php

namespace App\Filament\Resources\VendorCategories\Pages;

use App\Filament\Resources\VendorCategories\VendorCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendorCategories extends ListRecords
{
    protected static string $resource = VendorCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

