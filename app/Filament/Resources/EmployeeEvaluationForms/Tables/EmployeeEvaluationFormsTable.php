<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
class EmployeeEvaluationFormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('jobDescription.job_title')
                    ->label('Job Description')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('criteria')
                    ->label('Criteria')
                    ->getStateUsing(fn ($record) => count($record->criteria ?? []))
                    ->formatStateUsing(fn ($state) => $state . ' ' . ($state === 1 ? 'criterion' : 'criteria')),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
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
                    \Filament\Actions\RestoreBulkAction::make()
                ])
            ]);
    }
}
