<?php

namespace App\Filament\Resources\EmployeeEvaluations\Tables;

use App\Filament\Resources\Tasks\Actions\TasksAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EmployeeEvaluationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('evaluation_year')->label('Year')->sortable(),
                Tables\Columns\TextColumn::make('final_score')->label('Final Score')->suffix('%')->sortable(),
                Tables\Columns\TextColumn::make('criteria')
                    ->label('Criteria')
                    ->getStateUsing(fn ($record) => count($record->criteria ?? []))
                    ->formatStateUsing(fn ($state) => $state.' '.($state === 1 ? 'criterion' : 'criteria')),
                Tables\Columns\TextColumn::make('due_by')->date(),
                Tables\Columns\TextColumn::make('performed_on')->date(),
                Tables\Columns\TextColumn::make('performedBy.name')->label('Evaluator'),
                Tables\Columns\TextColumn::make('employeeEvaluationForm.jobDescription.job_title')->label('Evaluation Form')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('approval_source')->label('Approval Outcome')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([Tables\Filters\TrashedFilter::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                CommentsAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
