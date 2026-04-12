<?php

namespace App\Filament\Resources\CustomerLeads\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CustomerLeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('first_name'),
                \Filament\Infolists\Components\TextEntry::make('middle_name'),
                \Filament\Infolists\Components\TextEntry::make('last_name'),
                \Filament\Infolists\Components\TextEntry::make('full_name'),
                \Filament\Infolists\Components\TextEntry::make('email'),
                \Filament\Infolists\Components\TextEntry::make('phone'),
                \Filament\Infolists\Components\TextEntry::make('source'),
                \Filament\Infolists\Components\TextEntry::make('topic_interest'),
                \Filament\Infolists\Components\TextEntry::make('product_interest'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('last_contacted_at')->dateTime(),
            ]);
    }
}
