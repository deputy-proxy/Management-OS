<?php

namespace App\Filament\Resources\JobDescriptions\Tables;

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

class JobDescriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('job_title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('job_level'),
                Tables\Columns\TextColumn::make('lineManager.job_title')->label('Line Manager')->placeholder('Not assigned')->searchable(),
                Tables\Columns\TextColumn::make('job_role'),
                // Tables\Columns\TextColumn::make('relevant_indicators'),
                Tables\Columns\TextColumn::make('document_code'),
                Tables\Columns\TextColumn::make('document'),
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
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
