<?php

namespace App\Filament\Resources\DigitalAssets\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class DigitalAssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('name'),
                \Filament\Infolists\Components\TextEntry::make('type'),
                \Filament\Infolists\Components\TextEntry::make('email_used'),
                \Filament\Infolists\Components\TextEntry::make('username'),
                \Filament\Infolists\Components\TextEntry::make('password'),
                \Filament\Infolists\Components\TextEntry::make('vendor_id'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('registered_on')->dateTime(),
            ]);
    }
}
