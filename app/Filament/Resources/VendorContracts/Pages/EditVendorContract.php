<?php

namespace App\Filament\Resources\VendorContracts\Pages;

use App\Filament\Resources\VendorContracts\VendorContractResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVendorContract extends EditRecord
{
    protected static string $resource = VendorContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

