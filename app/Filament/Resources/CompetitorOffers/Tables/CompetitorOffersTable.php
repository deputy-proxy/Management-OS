<?php

namespace App\Filament\Resources\CompetitorOffers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;


class CompetitorOffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('competitor.name')
                    ->label('Competitor')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('offer_type'),
                Tables\Columns\TextColumn::make('offer_description'),
                Tables\Columns\TextColumn::make('offer_price'),
                Tables\Columns\TextColumn::make('our_similar_offer'),
                Tables\Columns\TextColumn::make('their_differentiator'),
                Tables\Columns\TextColumn::make('last_check')->dateTime(),
                Tables\Columns\TextColumn::make('status'),
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
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}
