<?php

namespace App\Filament\Resources\TestQuestions\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class TestQuestionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('question'),
                \Filament\Infolists\Components\TextEntry::make('question_type'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('correct_answer'),
                \Filament\Infolists\Components\KeyValueEntry::make('data'),
            ]);
    }
}
