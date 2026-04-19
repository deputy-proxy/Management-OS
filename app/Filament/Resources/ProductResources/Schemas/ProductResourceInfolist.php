<?php

namespace App\Filament\Resources\ProductResources\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class ProductResourceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('name'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('allocated_percentage'),
                \Filament\Infolists\Components\TextEntry::make('month')
                    ->dateTime()
            ]);
    }
}
