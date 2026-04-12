<?php

namespace App\Filament\Resources\CustomerContracts\Pages;

use App\Filament\Resources\CustomerContracts\CustomerContractResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerContract extends CreateRecord
{
    protected static string $resource = CustomerContractResource::class;
}

