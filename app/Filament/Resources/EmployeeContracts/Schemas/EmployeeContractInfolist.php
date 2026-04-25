<?php

namespace App\Filament\Resources\EmployeeContracts\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class EmployeeContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('employee_id'),
                \Filament\Infolists\Components\TextEntry::make('start_date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_date')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('contract_type'),
                \Filament\Infolists\Components\TextEntry::make('base_salary'),
                \Filament\Infolists\Components\TextEntry::make('hourly_rate'),
                \Filament\Infolists\Components\KeyValueEntry::make('clauses')
            ]);
    }
}
