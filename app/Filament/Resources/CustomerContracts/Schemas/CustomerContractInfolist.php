<?php

namespace App\Filament\Resources\CustomerContracts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('source_customer_lead_id')->label('Source lead'),
                TextEntry::make('contract_code'),
                TextEntry::make('start_date')->dateTime(),
                TextEntry::make('end_date')->dateTime(),
                TextEntry::make('document'),
                TextEntry::make('signed_at')->dateTime(),
            ]);
    }
}