<?php

namespace App\Filament\Resources\VendorCategories\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class VendorCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
            ]);
    }
}
