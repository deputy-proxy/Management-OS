<?php

namespace App\Filament\Resources\VendorInvoices\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class VendorInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('vendor_id'),
                \Filament\Infolists\Components\TextEntry::make('vendor_contract_id'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('vat'),
                \Filament\Infolists\Components\TextEntry::make('currency'),
                \Filament\Infolists\Components\TextEntry::make('month'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('invoice'),
                \Filament\Infolists\Components\TextEntry::make('invoice_number'),
                \Filament\Infolists\Components\TextEntry::make('identifier')
            ]);
    }
}
