<?php

namespace App\Filament\Resources\Camps\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CampInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('description'),
                TextEntry::make('location'),
                TextEntry::make('start_on')
                    ->dateTime(),
                TextEntry::make('end_on')
                    ->dateTime(),
                TextEntry::make('productPrice.price')->label('Price'),
                TextEntry::make('productPrice.currency')->label('Currency'),
            ]);
    }
}