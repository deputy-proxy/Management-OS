<?php

namespace App\Filament\Resources\ProductRevenues\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;


class ProductRevenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.title')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('customerInvoice.identifier')
                    ->label('Customer Invoice')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('month')->dateTime(),
                Tables\Columns\TextColumn::make('amount'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}
