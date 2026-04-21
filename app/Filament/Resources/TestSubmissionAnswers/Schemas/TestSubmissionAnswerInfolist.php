<?php

namespace App\Filament\Resources\TestSubmissionAnswers\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class TestSubmissionAnswerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('test_submission_id'),
                \Filament\Infolists\Components\TextEntry::make('test_question_id'),
                \Filament\Infolists\Components\KeyValueEntry::make('answer'),
                \Filament\Infolists\Components\TextEntry::make('points'),
                \Filament\Infolists\Components\TextEntry::make('graded_by'),
                \Filament\Infolists\Components\KeyValueEntry::make('metadata')
            ]);
    }
}
