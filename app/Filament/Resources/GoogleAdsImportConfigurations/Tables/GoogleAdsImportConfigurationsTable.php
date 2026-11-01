<?php

namespace App\Filament\Resources\GoogleAdsImportConfigurations\Tables;

use App\Models\GoogleAdsImportConfiguration;
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
            TextColumn::make('last_error_code')->placeholder('No error'),
            TextColumn::make('last_checked_at')->dateTime()->placeholder('Not checked'),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
