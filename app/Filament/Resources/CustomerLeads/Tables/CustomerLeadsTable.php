<?php

namespace App\Filament\Resources\CustomerLeads\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;


class CustomerLeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('middle_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('source'),
                Tables\Columns\TextColumn::make('topic_interest'),
                Tables\Columns\TextColumn::make('product_interest'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('last_contacted_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(),
                DeleteAction::make(),
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
