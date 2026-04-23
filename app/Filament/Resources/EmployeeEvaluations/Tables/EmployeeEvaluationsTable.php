<?php

namespace App\Filament\Resources\EmployeeEvaluations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
class EmployeeEvaluationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('criteria'),
                Tables\Columns\TextColumn::make('due_by')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('performed_on')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('performedBy.name')
                    ->label('Performed By'),
                Tables\Columns\TextColumn::make('employeeEvaluationForm.job_description_id')
                    ->label('Employee Evaluation Form')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
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
