<?php

namespace App\Filament\Resources\ProductResourceAllocations\Pages;

use App\Filament\Resources\ProductResourceAllocations\ProductResourceAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductResourceAllocations extends ListRecords
{
    protected static string $resource = ProductResourceAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

