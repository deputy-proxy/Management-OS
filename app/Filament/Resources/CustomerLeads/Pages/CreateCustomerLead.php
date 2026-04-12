<?php

namespace App\Filament\Resources\CustomerLeads\Pages;

use App\Filament\Resources\CustomerLeads\CustomerLeadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerLead extends CreateRecord
{
    protected static string $resource = CustomerLeadResource::class;
}

