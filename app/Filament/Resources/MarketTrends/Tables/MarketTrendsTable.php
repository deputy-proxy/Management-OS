<?php

namespace App\Filament\Resources\MarketTrends\Tables;

use App\Models\MarketTrend;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

class MarketTrendsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('metric_key')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->numeric(decimalPlaces: 6)
                    ->sortable()
                    ->description(fn ($record): string => $record->unit),
                Tables\Columns\TextColumn::make('market')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('segment')
                    ->searchable(),
                Tables\Columns\TextColumn::make('period_start')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('observed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('data_status')->badge(),
                Tables\Columns\TextColumn::make('source_type')->badge(),
                Tables\Columns\TextColumn::make('freshness_status')->badge(),
                Tables\Columns\TextColumn::make('verification_status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'demand' => 'Demand',
                        'search_interest' => 'Search interest',
                        'competition' => 'Competition',
                        'pricing' => 'Pricing',
                        'growth' => 'Growth',
                    ]),
                Tables\Filters\SelectFilter::make('market')
                    ->options(fn (): array => MarketTrend::query()
                        ->distinct()
                        ->orderBy('market')
                        ->pluck('market', 'market')
                        ->all()),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'stale' => 'Stale',
                        'invalid' => 'Invalid',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
