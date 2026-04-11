<?php

namespace App\Filament\Resources\VendorContracts\Pages;

use App\Filament\Resources\VendorContracts\VendorContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendorContracts extends ListRecords
{
    protected static string $resource = VendorContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

