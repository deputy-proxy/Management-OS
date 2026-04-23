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
                \Filament\Infolists\Components\TextEntry::make('due_by')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('performed_on')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('performed_by'),
                \Filament\Infolists\Components\TextEntry::make('employee_evaluation_form_id'),
                \Filament\Infolists\Components\TextEntry::make('status')
            ]);
    }
}
