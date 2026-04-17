<?php

namespace App\Filament\Resources\Tasks\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class TaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('action'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('assigned_to'),
                \Filament\Infolists\Components\TextEntry::make('project_id'),
                \Filament\Infolists\Components\TextEntry::make('project_phase_id'),
                \Filament\Infolists\Components\TextEntry::make('taskable_type')
                    ->label('Taskable Type'),
                \Filament\Infolists\Components\TextEntry::make('taskable_id')
                    ->label('Taskable ID'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('due_date')->dateTime(),
            ]);
    }
}
