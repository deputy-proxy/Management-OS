<?php

namespace App\Filament\Resources\Processes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use Hexters\HexaLite\Models\HexaRole;
class ProcessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('steps')
                    ->label('Steps')
                        ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : count(json_decode($state, true) ?? []))
                    ->badge(),
                Tables\Columns\TextColumn::make('owner.full_name')
                    ->label('Owner'),
                Tables\Columns\TextColumn::make('main_topic'),
                Tables\Columns\TextColumn::make('visible_to')
                    ->formatStateUsing(fn ($state) => match(true) {
        is_int($state)    => $state,
        is_array($state)  => count($state),
        is_string($state) => count(json_decode($state, true) ?? []),
        default           => 0,
    })
                    ->badge()
                    ->separator(','),
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
