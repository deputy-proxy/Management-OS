<?php

namespace App\Filament\Resources\TestTypes\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class TestTypeInfolist
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
