<?php

namespace App\Filament\Resources\Groups\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class GroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('course_id'),
                \Filament\Infolists\Components\TextEntry::make('teacher_id'),
            ]);
    }
}
