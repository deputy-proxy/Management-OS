<?php

namespace App\Filament\Resources\ProductResourceAllocations\Pages;

use App\Filament\Resources\ProductResourceAllocations\ProductResourceAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductResourceAllocation extends ViewRecord
{
    protected static string $resource = ProductResourceAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

