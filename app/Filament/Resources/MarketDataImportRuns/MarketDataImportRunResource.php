<?php

namespace App\Filament\Resources\MarketDataImportRuns;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Resources\MarketDataImportRuns\Pages\ListMarketDataImportRuns;
use App\Filament\Resources\MarketDataImportRuns\Pages\ViewMarketDataImportRun;
use App\Models\MarketDataImportRun;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MarketDataImportRunResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = MarketDataImportRun::class;

    protected static ?string $cluster = MarketingCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Market Analysis';

    protected static ?string $navigationLabel = 'Market Data Imports';

    protected static ?int $navigationSort = 20;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    public function defineGates(): array
    {
        return [
            'market_intelligence_configuration.view' => __('Allows viewing market intelligence configuration'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('source_key'),
            TextEntry::make('status')->badge(),
            TextEntry::make('attempt_count'),
            TextEntry::make('records_received'),
            TextEntry::make('records_imported'),
            TextEntry::make('records_skipped'),
            TextEntry::make('error_type')->placeholder('None'),
            TextEntry::make('error_message')->placeholder('None')->columnSpanFull(),
            TextEntry::make('is_retryable')->badge(),
            TextEntry::make('started_at')->dateTime(),
            TextEntry::make('finished_at')->dateTime()->placeholder('In progress'),
            TextEntry::make('request_metadata')
                ->formatStateUsing(static fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                ->columnSpanFull(),
            TextEntry::make('observations_count')->state(fn (MarketDataImportRun $record): int => $record->observations()->count()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('started_at', 'desc')->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('source_key')->searchable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('attempt_count')->numeric(),
            TextColumn::make('records_received')->numeric(),
            TextColumn::make('records_imported')->numeric(),
            TextColumn::make('records_skipped')->numeric(),
            TextColumn::make('error_type')->placeholder('None'),
            TextColumn::make('is_retryable')->badge(),
            TextColumn::make('started_at')->dateTime()->sortable(),
        ])->recordActions([
            ViewAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketDataImportRuns::route('/'),
            'view' => ViewMarketDataImportRun::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('market_intelligence_configuration.view') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('Import runs can only be created through the market data import service.');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Market data import runs are audit records and cannot be edited.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Market data import runs are audit records and cannot be deleted.');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Market data import runs are audit records and cannot be deleted.');
    }
}
