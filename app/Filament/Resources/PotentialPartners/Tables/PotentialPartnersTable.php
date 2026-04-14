<?php

namespace App\Filament\Resources\PotentialPartners\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
class PotentialPartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('site'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('benefits_for_us'),
                Tables\Columns\TextColumn::make('benefits_for_them'),
                Tables\Columns\TextColumn::make('last_contact')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('contact_person_name'),
                Tables\Columns\TextColumn::make('contact_person_email'),
                Tables\Columns\TextColumn::make('contact_person_phone')
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
            ])
            ->recordActions([EditAction::make(),
                DeleteAction::make(),
                CommentsAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make()
                ])
            ]);
    }
}
