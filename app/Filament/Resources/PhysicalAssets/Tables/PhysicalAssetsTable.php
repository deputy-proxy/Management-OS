<?php

namespace App\Filament\Resources\PhysicalAssets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;


class PhysicalAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('asset_tag'),
                Tables\Columns\TextColumn::make('serial_number'),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('location'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('condition'),
                Tables\Columns\TextColumn::make('purchase_date')->dateTime(),
                Tables\Columns\TextColumn::make('purchase_price'),
                Tables\Columns\TextColumn::make('warranty_expiry_date')->dateTime(),
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
