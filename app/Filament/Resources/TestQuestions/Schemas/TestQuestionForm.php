<?php

namespace App\Filament\Resources\TestQuestions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TestQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Textarea::make('question')
                    ->required(),
                Forms\Components\TextInput::make('question_type')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\Textarea::make('correct_answer')
                    ->required(),
                Forms\Components\KeyValue::make('data')
                    ->required(),
            ]);
    }
}
