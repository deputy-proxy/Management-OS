<?php

namespace App\Filament\Resources\VendorCategories\Pages;

use App\Filament\Resources\VendorCategories\VendorCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorCategory extends ViewRecord
{
    protected static string $resource = VendorCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

