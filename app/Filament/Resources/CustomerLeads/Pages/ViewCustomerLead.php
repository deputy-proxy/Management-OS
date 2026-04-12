<?php

namespace App\Filament\Resources\CustomerLeads\Pages;

use App\Filament\Resources\CustomerLeads\CustomerLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerLead extends ViewRecord
{
    protected static string $resource = CustomerLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

