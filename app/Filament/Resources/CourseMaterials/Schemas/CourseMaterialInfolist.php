<?php

namespace App\Filament\Resources\CourseMaterials\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CourseMaterialInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('course_id'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\KeyValueEntry::make('content'),
                \Filament\Infolists\Components\TextEntry::make('added_by'),
            ]);
    }
}
