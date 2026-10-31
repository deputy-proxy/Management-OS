<?php

namespace App\Filament\Resources\ProductResourceAllocations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResourceAllocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('targetable_type')
                    ->label('Target type')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'product' => 'Product',
                        'project' => 'Project',
                        'paid_campaign' => 'Paid campaign',
                        default => 'Unknown',
                    }),
                Tables\Columns\TextColumn::make('targetable.title')
                    ->label('Target')
                    ->placeholder('Unknown target')
                    ->searchable(),
                Tables\Columns\TextColumn::make('productResource.name')
                    ->label('Product Resource')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('allocation_percent'),
                Tables\Columns\TextColumn::make('month')->dateTime(),
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
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
