<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('project_manager'),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('planned_start_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('planned_end_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('start_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_date')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('budget'),
            ]);
    }
}
