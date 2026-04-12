<?php

namespace App\Filament\Resources\CustomerInteractions\Pages;

use App\Filament\Resources\CustomerInteractions\CustomerInteractionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerInteraction extends CreateRecord
{
    protected static string $resource = CustomerInteractionResource::class;
}

