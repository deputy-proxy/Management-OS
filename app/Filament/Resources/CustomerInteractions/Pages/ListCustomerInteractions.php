<?php

namespace App\Filament\Resources\CustomerInteractions\Pages;

use App\Filament\Resources\CustomerInteractions\CustomerInteractionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerInteractions extends ListRecords
{
    protected static string $resource = CustomerInteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

