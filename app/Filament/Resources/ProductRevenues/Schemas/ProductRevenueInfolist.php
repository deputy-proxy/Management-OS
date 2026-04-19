<?php

namespace App\Filament\Resources\ProductRevenues\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProductRevenueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('product_id'),
                \Filament\Infolists\Components\TextEntry::make('customer_invoice_id'),
                \Filament\Infolists\Components\TextEntry::make('month')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('amount'),
            ]);
    }
}
