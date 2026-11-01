<?php

namespace App\Filament\Resources\GoogleAdsImportConfigurations\Tables;

use App\Models\GoogleAdsImportConfiguration;
use App\Services\GoogleAdsCampaignMetricsHealthService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GoogleAdsImportConfigurationsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('customer_id')->label('Ads account')->sortable(),
            TextColumn::make('selected_campaign_ids')->label('Campaign selection')->state(fn (GoogleAdsImportConfiguration $record): string => count($record->selected_campaign_ids ?? []) === 0 ? 'All campaigns' : count($record->selected_campaign_ids).' selected'),
            TextColumn::make('currency_code')->placeholder('Account-defined'),
            TextColumn::make('credential_status')->label('Connection')->state(fn (GoogleAdsImportConfiguration $record): string => str_replace('_', ' ', $record->configurationStatus()))->badge(),
            TextColumn::make('data_health')
                ->label('Data health')
                ->state(fn (GoogleAdsImportConfiguration $record): string => app(GoogleAdsCampaignMetricsHealthService::class)->inspect($record)['status'])
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'healthy' => 'success',
                    'retryable_error', 'stale', 'incomplete' => 'warning',
                    default => 'danger',
                }),
            TextColumn::make('latest_import_batch')
                ->label('Latest batch')
                ->state(fn (GoogleAdsImportConfiguration $record): string => $record->importBatches()->latest('id')->value('status') ?? 'never run')
                ->badge(),
            TextColumn::make('last_error_code')->placeholder('No error'),
            TextColumn::make('last_failure')->label('Failure detail')->state(fn (GoogleAdsImportConfiguration $record): ?string => $record->importBatches()->where('status', 'failed')->latest('id')->value('failure_message'))->placeholder('No failure')->limit(70),
            TextColumn::make('last_checked_at')->dateTime()->placeholder('Not checked'),
            TextColumn::make('last_success_at')
                ->label('Last successful import')
                ->state(fn (GoogleAdsImportConfiguration $record) => $record->importBatches()->where('status', 'completed')->latest('completed_at')->value('completed_at'))
                ->dateTime()
                ->placeholder('Never completed'),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
