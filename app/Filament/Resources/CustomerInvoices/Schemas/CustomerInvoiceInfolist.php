<?php

namespace App\Filament\Resources\CustomerInvoices\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CustomerInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
                \Filament\Infolists\Components\TextEntry::make('customer_contract_id'),
                \Filament\Infolists\Components\TextEntry::make('amount_without_vat'),
                \Filament\Infolists\Components\TextEntry::make('vat'),
                \Filament\Infolists\Components\TextEntry::make('month'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('invoice'),
            ]);
    }
}
