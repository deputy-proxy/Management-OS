<?php

namespace App\Filament\Resources\SessionAssistances\Tables;

use App\Filament\Resources\EmployeePaySlips\Actions\AddToPaySlipAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class SessionAssistancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session.identifier')
                    ->label('Session')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('teacher.full_name')
                    ->label('Teacher')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('assistance_date')->dateTime(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('review'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                AddToPaySlipAction::make(),
                CommentsAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
