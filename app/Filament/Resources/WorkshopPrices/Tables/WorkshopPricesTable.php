<?php

namespace App\Filament\Resources\WorkshopPrices\Tables;

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

class WorkshopPricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('workshop.title')
                    ->label('Workshop')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')->label('Amount'),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('valid_from')->label('Starts')->date(),
                Tables\Columns\TextColumn::make('valid_until')->label('Ends')->date(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(),
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