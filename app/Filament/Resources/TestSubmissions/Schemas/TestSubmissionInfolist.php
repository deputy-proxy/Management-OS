<?php

namespace App\Filament\Resources\TestSubmissions\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class TestSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('test_id'),
                \Filament\Infolists\Components\TextEntry::make('student_id'),
                \Filament\Infolists\Components\TextEntry::make('submission_timestamp')
                    ->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('status'),
                \Filament\Infolists\Components\TextEntry::make('score')
            ]);
    }
}
