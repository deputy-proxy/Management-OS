<?php

namespace App\Filament\Resources\Tasks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To'),
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Project')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('projectPhase.title')
                    ->label('Project Phase')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('dependencies.action')->label('Prerequisites')->listWithLineBreaks()->limitList(3),
                Tables\Columns\TextColumn::make('due_date')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
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
