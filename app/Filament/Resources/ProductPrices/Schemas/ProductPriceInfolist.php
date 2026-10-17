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
                Infolists\Components\TextEntry::make('price'),
                Infolists\Components\TextEntry::make('currency'),
                Infolists\Components\TextEntry::make('price_type'),
                Infolists\Components\TextEntry::make('priceable_type'),
                Infolists\Components\TextEntry::make('priceable.title')->label('Priced item'),
                Infolists\Components\TextEntry::make('valid_from')->date(),
                Infolists\Components\TextEntry::make('valid_until')->date(),
            ]);
    }
}