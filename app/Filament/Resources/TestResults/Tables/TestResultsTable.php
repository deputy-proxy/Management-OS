<?php

namespace App\Filament\Resources\TestResults\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;

class TestResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
 
                TextColumn::make('testSubmission.test.title')
                    ->label('Test')
                    ->searchable()
                    ->sortable(),
 
                TextColumn::make('results.earned_points')
                    ->label('Earned Points')
                    ->placeholder('—')
                    ->sortable(),
 
                TextColumn::make('results.total_points')
                    ->label('Total Points')
                    ->placeholder('—'),
 
                TextColumn::make('results.percentage')
                    ->label('Score')
                    ->suffix('%')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state === null    => 'gray',
                        $state >= 80      => 'success',
                        $state >= 60      => 'warning',
                        default           => 'danger',
                    })
                    ->sortable(),
 
                IconColumn::make('results.passed')
                    ->label('Passed')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
 
                TextColumn::make('teacher.full_name')
                    ->label('Graded By')
                    ->toggleable(),
 
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
