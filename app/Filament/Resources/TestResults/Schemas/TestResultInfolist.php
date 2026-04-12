<?php

namespace App\Filament\Resources\TestResults\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class TestResultInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('test_submission_id'),
                \Filament\Infolists\Components\TextEntry::make('student_id'),
                \Filament\Infolists\Components\KeyValueEntry::make('results'),
                \Filament\Infolists\Components\TextEntry::make('teacher_id'),
            ]);
    }
}
