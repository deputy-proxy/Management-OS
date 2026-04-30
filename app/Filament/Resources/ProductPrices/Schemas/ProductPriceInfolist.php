<?php

namespace App\Filament\Resources\ProductPrices\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProductPriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('price'),
                \Filament\Infolists\Components\TextEntry::make('currency'),
            ]);
    }
}
