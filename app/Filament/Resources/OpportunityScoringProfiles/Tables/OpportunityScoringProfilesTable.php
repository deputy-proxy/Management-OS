<?php

namespace App\Filament\Resources\OpportunityScoringProfiles\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class OpportunityScoringProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('version')->sortable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('factors')->formatStateUsing(fn ($state): string => (string) count($state ?? [])),
            Tables\Columns\IconColumn::make('is_provisional')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->recordActions([
            EditAction::make()->visible(fn ($record): bool => $record->status === 'draft'),
            DeleteAction::make()->visible(fn ($record): bool => $record->status === 'draft'),
        ]);
    }
}
