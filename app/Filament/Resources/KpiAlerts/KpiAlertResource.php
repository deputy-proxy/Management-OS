<?php

namespace App\Filament\Resources\KpiAlerts;

use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Resources\KpiAlerts\Pages\ListKpiAlerts;
use App\Filament\Resources\KpiAlerts\Pages\ViewKpiAlert;
use App\Filament\Resources\KpiAlerts\Schemas\KpiAlertInfolist;
use App\Filament\Resources\KpiAlerts\Tables\KpiAlertsTable;
use App\Models\KpiAlert;
use App\Services\KpiAlertService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class KpiAlertResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = KpiAlert::class;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Executive Overview';

    protected static ?string $navigationLabel = 'KPI Alerts';

    protected static ?string $recordTitleAttribute = 'dedupe_key';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';

    public function defineGates(): array
    {
        return [
            'kpi_alert.index' => __('Allows viewing all KPI alerts'),
            'kpi_alert.view_own' => __('Allows viewing owned KPI alerts'),
            'kpi_alert.manage' => __('Allows acknowledging and resolving all KPI alerts'),
            'kpi_alert.manage_own' => __('Allows acknowledging and resolving owned KPI alerts'),
            'kpi_alert.notify' => __('Allows receiving KPI alert notifications'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(KpiAlertService::class)->queryForCurrentUser();
    }

    public static function infolist(Schema $schema): Schema
    {
        return KpiAlertInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KpiAlertsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKpiAlerts::route('/'),
            'view' => ViewKpiAlert::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('kpi_alert.index') || hexa()->can('kpi_alert.view_own')
            ? Response::allow()
            : Response::deny();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        $canView = hexa()->can('kpi_alert.index')
            || ((int) $record->getAttribute('owner_id') === (int) Auth::id() && hexa()->can('kpi_alert.view_own'));

        return $canView ? Response::allow() : Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny(__('KPI alert lifecycle records cannot be deleted.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny(__('KPI alert lifecycle records cannot be deleted.'));
    }
}
