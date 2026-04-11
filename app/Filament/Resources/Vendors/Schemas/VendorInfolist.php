<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class VendorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('name'),
                \Filament\Infolists\Components\TextEntry::make('vendor_category_id'),
                \Filament\Infolists\Components\TextEntry::make('start_date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('website')
            ]);
    }
}
