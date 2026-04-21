<?php

namespace App\Filament\Resources\TestSubmissionAnswers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
class TestSubmissionAnswersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('testSubmission.id')
                    ->label('Test Submission')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('testQuestion.question')
                    ->label('Test Question')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('answer'),
                Tables\Columns\TextColumn::make('points'),
                Tables\Columns\TextColumn::make('gradedBy.full_name')
                    ->label('Graded By'),
                Tables\Columns\TextColumn::make('metadata')
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
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
