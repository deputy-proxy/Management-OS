<?php

namespace App\Filament\Resources\TestSubmissionAnswers\Schemas;

use App\Models\TestSubmissionAnswer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TestSubmissionAnswerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('test_submission_id'),
                TextEntry::make('test_question_id'),
                TextEntry::make('answer')
                    ->state(fn (TestSubmissionAnswer $record): string => self::formatValue($record->answer))
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('points'),
                TextEntry::make('graded_by'),
                TextEntry::make('metadata')
                    ->state(fn (TestSubmissionAnswer $record): string => self::formatValue($record->metadata))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
    }
}
