<?php

namespace App\Filament\Resources\CustomerContracts\Pages;

use App\Filament\Resources\CustomerContracts\CustomerContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerContract extends ViewRecord
{
    protected static string $resource = CustomerContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

