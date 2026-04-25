<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('full_name'),
                \Filament\Infolists\Components\TextEntry::make('first_name'),
                \Filament\Infolists\Components\TextEntry::make('last_name'),
                \Filament\Infolists\Components\TextEntry::make('date_of_birth')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('user_id')
            ]);
    }
}
