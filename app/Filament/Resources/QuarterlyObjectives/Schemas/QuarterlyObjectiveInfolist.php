<?php

namespace App\Filament\Resources\QuarterlyObjectives\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class QuarterlyObjectiveInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('employee_id'),
                \Filament\Infolists\Components\TextEntry::make('objectives'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('quarter'),
                \Filament\Infolists\Components\TextEntry::make('status'),
            ]);
    }
}
