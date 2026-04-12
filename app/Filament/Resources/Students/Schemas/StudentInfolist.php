<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('first_name'),
                \Filament\Infolists\Components\TextEntry::make('middle_name'),
                \Filament\Infolists\Components\TextEntry::make('last_name'),
                \Filament\Infolists\Components\TextEntry::make('full_name'),
                \Filament\Infolists\Components\TextEntry::make('gender'),
                \Filament\Infolists\Components\TextEntry::make('date_of_birth')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
                \Filament\Infolists\Components\TextEntry::make('phone_number'),
                \Filament\Infolists\Components\TextEntry::make('email'),
                \Filament\Infolists\Components\TextEntry::make('address')
            ]);
    }
}
