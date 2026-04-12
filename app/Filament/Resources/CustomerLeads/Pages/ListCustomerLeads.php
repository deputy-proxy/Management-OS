<?php

namespace App\Filament\Resources\CustomerLeads\Pages;

use App\Filament\Resources\CustomerLeads\CustomerLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerLeads extends ListRecords
{
    protected static string $resource = CustomerLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

