<?php

namespace App\Filament\Resources\TestSimulations\Tables;

use App\Filament\Resources\EmployeePaySlips\Actions\AddToPaySlipAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class TestSimulationsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->sortable(),
            TextColumn::make('teacher.full_name')->label('Teacher')->searchable(),
            TextColumn::make('test.title')->label('Test')->placeholder('—'),
            TextColumn::make('group.title')->label('Group')->placeholder('—'),
            TextColumn::make('date')->dateTime()->sortable(),
            TextColumn::make('number_of_hours')->label('Hours'),
            TextColumn::make('status')->badge(),
        ])->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                AddToPaySlipAction::make(),
                TasksAction::make(),
                CommentsAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ])]);
    }
}
