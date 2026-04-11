<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('name'),
                \Filament\Infolists\Components\TextEntry::make('customer_type'),
                \Filament\Infolists\Components\TextEntry::make('cnp_cui'),
                \Filament\Infolists\Components\TextEntry::make('address'),
                \Filament\Infolists\Components\TextEntry::make('email'),
            ]);
    }
}
