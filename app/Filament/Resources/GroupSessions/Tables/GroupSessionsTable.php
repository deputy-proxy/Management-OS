<?php

namespace App\Filament\Resources\GroupSessions\Tables;

use App\Filament\Resources\GroupSessions\Actions\InputSessionAttendanceAction;
use App\Filament\Resources\GroupSessions\Actions\ProvideSessionFeedbackAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class GroupSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group.title')
                    ->label('Group')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('course.title')
                    ->label('Course')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('teacher.full_name')
                    ->label('Teacher')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_on')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('end_on')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('identifier'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                InputSessionAttendanceAction::make(),
                ProvideSessionFeedbackAction::make(),
                CommentsAction::make(),
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
