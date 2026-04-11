<?php

namespace App\Filament\Resources\VendorContracts\Pages;

use App\Filament\Resources\VendorContracts\VendorContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorContract extends ViewRecord
{
    protected static string $resource = VendorContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

