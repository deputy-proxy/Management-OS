<?php

namespace App\Filament\Resources\PhysicalAssets\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class PhysicalAssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('name'),
                \Filament\Infolists\Components\TextEntry::make('asset_tag'),
                \Filament\Infolists\Components\TextEntry::make('serial_number'),
                \Filament\Infolists\Components\TextEntry::make('category'),
                \Filament\Infolists\Components\TextEntry::make('location'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('condition'),
                \Filament\Infolists\Components\TextEntry::make('purchase_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('purchase_price'),
                \Filament\Infolists\Components\TextEntry::make('warranty_expiry_date')->dateTime(),
            ]);
    }
}
