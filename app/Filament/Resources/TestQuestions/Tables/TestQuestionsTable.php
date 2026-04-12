<?php

namespace App\Filament\Resources\TestQuestions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;


class TestQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question'),
                Tables\Columns\TextColumn::make('question_type'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('correct_answer'),
                Tables\Columns\TextColumn::make('data'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
