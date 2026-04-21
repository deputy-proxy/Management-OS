<?php

namespace App\Filament\Resources\TestSubmissionResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnswersRelationManager extends RelationManager
{
    protected static string $relationship = 'answers';

    protected static ?string $title = 'Answers & Grading';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('Question & Answer')
                ->schema([
                    Select::make('test_question_id')
                        ->label('Question')
                        ->relationship('testQuestion', 'question')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),

                    Textarea::make('answer')
                        ->label('Student Answer')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('Grading')
                ->schema([
                    TextInput::make('points')
                        ->label('Points Awarded')
                        ->numeric()
                        ->minValue(0)
                        ->nullable(),

                    Select::make('graded_by')
                        ->label('Graded By')
                        ->relationship('gradedBy', 'full_name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('test_question_id')
            ->columns([
                TextColumn::make('testQuestion.question')
                    ->label('Question')
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('testQuestion.question_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'short_answer'    => 'info',
                        'long_answer'     => 'info',
                        'multiple_choice' => 'success',
                        'multiple_select' => 'success',
                        'true_false'      => 'warning',
                        default           => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'short_answer'    => 'Short Answer',
                        'long_answer'     => 'Long Answer',
                        'multiple_choice' => 'Multiple Choice',
                        'multiple_select' => 'Multiple Select',
                        'true_false'      => 'True / False',
                        default           => $state,
                    }),

                TextColumn::make('answer')
                    ->label('Answer')
                    ->limit(60)
                    ->placeholder('—'),

                TextColumn::make('points')
                    ->label('Points')
                    ->placeholder('Not graded')
                    ->badge()
                    ->color(fn ($state): string => $state === null ? 'gray' : 'success'),

                TextColumn::make('gradedBy.full_name')
                    ->label('Graded By')
                    ->placeholder('—'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Grade'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}