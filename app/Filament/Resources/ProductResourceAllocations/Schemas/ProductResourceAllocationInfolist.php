<?php

namespace App\Filament\Resources\ProductResourceAllocations\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProductResourceAllocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('product_id'),
                \Filament\Infolists\Components\TextEntry::make('product_resource_id'),
                \Filament\Infolists\Components\TextEntry::make('allocation_percent'),
                \Filament\Infolists\Components\TextEntry::make('month')->dateTime(),
            ]);
    }
}
