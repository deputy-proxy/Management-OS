<?php

namespace App\Filament\Resources\GoogleAdsImportConfigurations\Schemas;

use App\Models\GoogleAdsImportConfiguration;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GoogleAdsImportConfigurationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account and campaigns')->schema([
                TextInput::make('name')->required()->maxLength(120)->unique(ignoreRecord: true),
                TextInput::make('customer_id')->required()->mask('999-999-9999')->helperText('10-digit Google Ads account customer ID.'),
                TextInput::make('login_customer_id')->mask('999-999-9999')->helperText('Optional manager account ID.'),
                TagsInput::make('selected_campaign_ids')->placeholder('Add numeric campaign IDs')->helperText('Leave empty to include all campaigns. Maximum 500 unique campaign IDs.'),
                TextInput::make('currency_code')->maxLength(3)->minLength(3)->helperText('Optional expected account currency, for example RON. Values are never converted.'),
            ])->columns(2),
            Section::make('Date range and metric mapping')->schema([
                TextInput::make('date_range_days')->numeric()->required()->default(30)->minValue(1)->maxValue(90)->helperText('Default bounded window used by scheduled/manual runs.'),
                DatePicker::make('date_range_start')->native(false)->helperText('Optional fixed start date. Set together with the end date.'),
                DatePicker::make('date_range_end')->native(false)->helperText('Optional fixed end date. Both dates must be within 90 days.'),
                Hidden::make('metric_mapping')->default(GoogleAdsImportConfiguration::METRIC_MAPPING),
                Placeholder::make('metric_mapping_contract')
                    ->label('Stored metric mapping')
                    ->content('Campaign ID/name, account-local date, impressions, clicks, cost micros, conversions, and account currency use the fixed v26 canonical mapping.'),
            ])->columns(2),
            Section::make('Credentials and status')->schema([
                Select::make('credential_reference')
                    ->options(['env:GOOGLE_ADS' => 'Deployment secret bundle (env:GOOGLE_ADS)'])
                    ->placeholder('Not configured')
                    ->helperText('Stores only a reference. OAuth client ID, client secret, and refresh token remain in the deployment secret manager and are never entered or stored here.'),
                Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }
}
