<?php

namespace App\Filament\Resources\VendorContracts\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class VendorContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('vendor_id'),
                \Filament\Infolists\Components\TextEntry::make('contract_code'),
                \Filament\Infolists\Components\TextEntry::make('start_date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('document')
            ]);
    }
}
