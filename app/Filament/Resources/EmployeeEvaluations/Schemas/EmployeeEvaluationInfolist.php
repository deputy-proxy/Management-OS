<?php

namespace App\Filament\Resources\EmployeeEvaluations\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class EmployeeEvaluationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('employee_id'),
                \Filament\Infolists\Components\KeyValueEntry::make('criteria'),
            ]);
    }
}
