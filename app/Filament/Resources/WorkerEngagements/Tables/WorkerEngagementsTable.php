<?php

namespace App\Filament\Resources\WorkerEngagements\Tables;

use App\Models\WorkerEngagement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WorkerEngagementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('engagement_type')
                    ->label('Arrangement')
                    ->formatStateUsing(fn (?string $state): string => WorkerEngagement::TYPES[$state ?? 'unknown'] ?? 'Unknown')
                    ->badge()->searchable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn (?string $state): string => WorkerEngagement::STATUSES[$state ?? 'unresolved'] ?? 'Unresolved')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'ended' => 'gray',
                        'on_hold' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('employee.full_name')->label('Employee')->placeholder('Not linked')->searchable(),
                TextColumn::make('vendor.name')->label('Vendor / contracting party')->placeholder('Not linked')->searchable(),
                TextColumn::make('start_date')->date()->sortable(),
                TextColumn::make('end_date')->date()->sortable()->placeholder('Open-ended'),
                TextColumn::make('source_reference')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('engagement_type')->options(WorkerEngagement::TYPES),
                SelectFilter::make('status')->options(WorkerEngagement::STATUSES),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
