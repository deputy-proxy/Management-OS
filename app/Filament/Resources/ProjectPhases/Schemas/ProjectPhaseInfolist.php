<?php

namespace App\Filament\Resources\ProjectPhases\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProjectPhaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('project_id'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('start_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('order'),
            ]);
    }
}
