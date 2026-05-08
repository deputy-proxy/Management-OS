<?php

namespace App\Filament\Resources\Tests\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Resources\TestQuestions\TestQuestionResource;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Questions';

    public function form(Schema $schema): Schema
    {
        // This form is used when editing pivot data (points, order) inline
        return $schema
        ->components([
            TextInput::make('points')
                ->label('Points')
                ->numeric()
                ->minValue(0)
                ->default(1.00)
                ->required(),

            TextInput::make('order')
                ->label('Order')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->reorderable('order') // drag-to-reorder using the pivot order column
            ->columns([
                TextColumn::make('question')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('question_type')
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

                TextColumn::make('pivot.points')
                    ->label('Points')
                    ->sortable(),

                TextColumn::make('pivot.order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->searchable()
                            ->multiple(),

                        TextInput::make('points')
                            ->label('Points')
                            ->numeric()
                            ->minValue(0)
                            ->default(1.00)
                            ->required(),

                        TextInput::make('order')
                            ->label('Order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ]),
                CreateAction::make('test_question')
                    ->model(\App\Models\TestQuestion::class)
                    ->modalHeading('Request Medical Leave')
                        ->schema(fn (\Filament\Schemas\Schema $schema) => 
                            TestQuestionResource::form($schema)
                        ),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit Pivot'),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}