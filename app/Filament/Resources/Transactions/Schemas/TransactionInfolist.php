<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('financial_account_id'),
                \Filament\Infolists\Components\TextEntry::make('vendor_invoice_id'),
                \Filament\Infolists\Components\TextEntry::make('transaction_type'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('payment_method'),
                \Filament\Infolists\Components\TextEntry::make('timestamp')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('customer_invoice_id'),
                \Filament\Infolists\Components\TextEntry::make('tax_id')
            ]);
    }
}
