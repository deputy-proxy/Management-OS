<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('customer_type'),
                TextEntry::make('cnp_cui'),
                TextEntry::make('address'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
            ]);
    }
}