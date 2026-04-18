<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('status'),
            ]);
    }
}
