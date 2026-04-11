<?php

namespace App\Filament\Resources\EmployeeSalaries\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class EmployeeSalaryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('employee_id'),
                \Filament\Infolists\Components\TextEntry::make('month'),
                \Filament\Infolists\Components\KeyValueEntry::make('items'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('due_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('paid_on')->dateTime(),
            ]);
    }
}
