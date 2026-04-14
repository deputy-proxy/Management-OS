<?php

namespace App\Filament\Resources\EmployeeTimesheets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
class EmployeeTimesheetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('time_start')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('time_end')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('number_of_hours'),
                Tables\Columns\TextColumn::make('groupSession.id')
                    ->label('Group Session')
                    ->sortable()
                    ->searchable()
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
            ])
            ->recordActions([EditAction::make(),
                DeleteAction::make(),
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
