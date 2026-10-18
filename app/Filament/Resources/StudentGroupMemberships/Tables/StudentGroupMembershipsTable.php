<?php

namespace App\Filament\Resources\StudentGroupMemberships\Tables;

use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class StudentGroupMembershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.full_name')->label('Student')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('group.title')->label('Group')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('started_on')->date()->sortable(),
                Tables\Columns\TextColumn::make('ended_on')->date()->placeholder('Current membership')->sortable(),
                Tables\Columns\TextColumn::make('membership_state')
                    ->label('State')
                    ->getStateUsing(fn ($record): string => $record->ended_on === null ? 'Current' : 'Ended')
                    ->badge(),
            ])
            ->recordActions([EditAction::make()]);
    }
}