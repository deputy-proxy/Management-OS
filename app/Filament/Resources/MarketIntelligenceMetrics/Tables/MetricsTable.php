<?php

namespace App\Filament\Resources\MarketIntelligenceMetrics\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class MetricsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category')->badge(),
            Tables\Columns\TextColumn::make('value_type')->badge(),
            Tables\Columns\TextColumn::make('allowed_units')->formatStateUsing(fn ($state): string => implode(', ', $state ?? [])),
            Tables\Columns\TextColumn::make('period_granularity')->badge(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\IconColumn::make('is_provisional')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
