<?php

namespace App\Filament\Resources\KpiDefinitions;

use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Resources\KpiDefinitions\Schemas\KpiDefinitionForm;
use App\Filament\Resources\KpiDefinitions\Tables\KpiDefinitionsTable;
use App\Models\KpiDefinition;
use App\Services\KpiDefinitionService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class KpiDefinitionResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = KpiDefinition::class;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Executive Overview';

    protected static ?string $navigationLabel = 'KPI Definitions';

    protected static ?string $recordTitleAttribute = 'label';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';

    public function defineGates(): array
    {
        return [
            'kpi_definition.index' => __('Allows viewing KPI definitions'),
            'kpi_definition.create' => __('Allows creating KPI definition drafts'),
            'kpi_definition.update' => __('Allows editing KPI definition drafts'),
            'kpi_definition.approve' => __('Allows approving, activating, and retiring KPI definitions'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(KpiDefinitionService::class)->queryForCurrentUser();
    }

    public static function form(Schema $schema): Schema
    {
        return KpiDefinitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KpiDefinitionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKpiDefinitions::route('/'),
            'create' => Pages\CreateKpiDefinition::route('/create'),
            'edit' => Pages\EditKpiDefinition::route('/{record}/edit'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('kpi_definition.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('kpi_definition.create') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return $record instanceof KpiDefinition && $record->status === KpiDefinition::STATUS_DRAFT && hexa()->can('kpi_definition.update')
            ? Response::allow()
            : Response::deny(__('Approved KPI definitions are immutable; create a new version.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny(__('KPI definitions are retained as versioned audit records.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny(__('KPI definitions are retained as versioned audit records.'));
    }
}
