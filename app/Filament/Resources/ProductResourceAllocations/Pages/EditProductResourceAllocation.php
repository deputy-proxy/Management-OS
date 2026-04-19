<?php

namespace App\Filament\Resources\ProductResourceAllocations\Pages;

use App\Filament\Resources\ProductResourceAllocations\ProductResourceAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductResourceAllocation extends EditRecord
{
    protected static string $resource = ProductResourceAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

