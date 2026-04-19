<?php

namespace App\Filament\Resources\ProductCosts\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProductCostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('product_id'),
                \Filament\Infolists\Components\TextEntry::make('product_resource_id'),
                \Filament\Infolists\Components\TextEntry::make('month')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('allocation_percent'),
                \Filament\Infolists\Components\TextEntry::make('resource_amount'),
                \Filament\Infolists\Components\TextEntry::make('allocated_amount'),
            ]);
    }
}
