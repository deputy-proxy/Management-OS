<?php

namespace App\Filament\Resources\TestQuestions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;


class TestQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->limit(80)
                    ->searchable()
                    ->wrap(),
 
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
                    })
                    ->sortable(),
 
                TextColumn::make('tests_count')
                    ->counts('tests')
                    ->label('Used in Tests')
                    ->sortable(),
 
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('question_type')
                    ->options([
                        'short_answer'    => 'Short Answer',
                        'long_answer'     => 'Long Answer',
                        'multiple_choice' => 'Multiple Choice',
                        'multiple_select' => 'Multiple Select',
                        'true_false'      => 'True / False',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                CommentsAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}
