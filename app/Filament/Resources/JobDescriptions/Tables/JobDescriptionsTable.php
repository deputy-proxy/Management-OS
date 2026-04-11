<?php

namespace App\Filament\Resources\JobDescriptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;


class JobDescriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('job_title'),
                Tables\Columns\TextColumn::make('job_level'),
                Tables\Columns\TextColumn::make('job_role'),
                //Tables\Columns\TextColumn::make('relevant_indicators'),
                Tables\Columns\TextColumn::make('document_code'),
                Tables\Columns\TextColumn::make('document'),
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
