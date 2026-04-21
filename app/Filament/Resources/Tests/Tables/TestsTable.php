<?php

namespace App\Filament\Resources\Tests\Tables;

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

class TestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
 
                TextColumn::make('testType.title')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
 
                TextColumn::make('level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'beginner'     => 'success',
                        'intermediate' => 'warning',
                        'advanced'     => 'danger',
                        default        => 'gray',
                    })
                    ->sortable(),
 
                TextColumn::make('topic')
                    ->searchable()
                    ->toggleable(),
 
                TextColumn::make('time_limit')
                    ->label('Time Limit')
                    ->suffix(' min')
                    ->sortable(),
 
                TextColumn::make('passing_score')
                    ->label('Passing Score')
                    ->suffix('%')
                    ->sortable(),
 
                TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Questions')
                    ->sortable(),
 
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->toggleable(),
 
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('test_type_id')
                    ->label('Test Type')
                    ->relationship('testType', 'title'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                CommentsAction::make()
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make()
                ])
            ]);
    }
}
