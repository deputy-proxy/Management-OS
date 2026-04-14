<?php

namespace App\Filament\Resources\VendorCategories\Pages;

use App\Filament\Resources\VendorCategories\VendorCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditVendorCategory extends EditRecord
{
    protected static string $resource = VendorCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

