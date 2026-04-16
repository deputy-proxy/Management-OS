<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class EmployeeMedicalLeaveInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('employee_id'),
                \Filament\Infolists\Components\TextEntry::make('start_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('document'),
            ]);
    }
}
