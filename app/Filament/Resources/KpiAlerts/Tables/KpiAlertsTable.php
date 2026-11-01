<?php

namespace App\Filament\Resources\KpiAlerts\Tables;

use App\Models\KpiAlert;
use App\Services\KpiAlertService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KpiAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('definition.label')->label('KPI')->searchable(),
                TextColumn::make('severity')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('observed_value')->label('Observed')->numeric(decimalPlaces: 2),
                TextColumn::make('trigger_threshold')->label('Threshold')->numeric(decimalPlaces: 2),
                TextColumn::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                TextColumn::make('evidence.source_record_ids')
                    ->label('Source record IDs')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) && $state !== [] ? implode(', ', $state) : 'Unavailable'),
                TextColumn::make('first_seen_at')->dateTime()->sortable(),
                TextColumn::make('last_seen_at')->dateTime()->sortable(),
                TextColumn::make('evidence_url')
                    ->label('Evidence')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Open evidence' : 'Unavailable')
                    ->url(fn (KpiAlert $record): ?string => $record->evidence_url)
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (KpiAlert $record): bool => $record->status === KpiAlert::STATUS_OPEN && app(KpiAlertService::class)->canManage($record))
                    ->action(fn (KpiAlert $record): KpiAlert => app(KpiAlertService::class)->acknowledge($record)),
                Action::make('resolve')
                    ->label('Resolve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (KpiAlert $record): bool => in_array($record->status, [KpiAlert::STATUS_OPEN, KpiAlert::STATUS_ACKNOWLEDGED], true) && app(KpiAlertService::class)->canManage($record))
                    ->action(fn (KpiAlert $record): KpiAlert => app(KpiAlertService::class)->resolve($record)),
            ])
            ->defaultSort('last_seen_at', 'desc');
    }
}
