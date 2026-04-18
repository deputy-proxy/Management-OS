<?php

namespace App\Filament\Resources\IndividualSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
class IndividualSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('identifier'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('course.title')
                    ->label('Course')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('teacher.full_name')
                    ->label('Teacher'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('status')
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
