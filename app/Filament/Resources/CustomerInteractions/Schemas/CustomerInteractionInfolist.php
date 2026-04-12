<?php

namespace App\Filament\Resources\CustomerInteractions\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class CustomerInteractionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
                \Filament\Infolists\Components\TextEntry::make('initiator'),
                \Filament\Infolists\Components\TextEntry::make('reason'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('timestamp')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('interaction_rating')
            ]);
    }
}
