<?php

namespace App\Filament\Resources\WorkerEngagements;

use App\Filament\Clusters\General\GeneralCluster;
use App\Filament\Resources\WorkerEngagements\Pages\CreateWorkerEngagement;
use App\Filament\Resources\WorkerEngagements\Pages\EditWorkerEngagement;
use App\Filament\Resources\WorkerEngagements\Pages\ListWorkerEngagements;
use App\Filament\Resources\WorkerEngagements\Pages\ViewWorkerEngagement;
use App\Filament\Resources\WorkerEngagements\Schemas\WorkerEngagementForm;
use App\Filament\Resources\WorkerEngagements\Schemas\WorkerEngagementInfolist;
use App\Filament\Resources\WorkerEngagements\Tables\WorkerEngagementsTable;
use App\Models\WorkerEngagement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class WorkerEngagementResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = WorkerEngagement::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 7;

    protected static ?string $cluster = GeneralCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-briefcase';

    public function defineGates(): array
    {
        return [
            'worker_engagement.index' => __('Allows viewing worker engagements'),
            'worker_engagement.create' => __('Allows creating worker engagements'),
            'worker_engagement.update' => __('Allows updating worker engagements'),
            'worker_engagement.delete' => __('Allows deleting worker engagements'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return hexa()->can('worker_engagement.index')
            ? (string) static::getModel()::query()->where('status', 'unresolved')->count()
            : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unresolved worker engagements';
    }

    public static function form(Schema $schema): Schema
    {
        return WorkerEngagementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkerEngagementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkerEngagementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkerEngagements::route('/'),
            'create' => CreateWorkerEngagement::route('/create'),
            'edit' => EditWorkerEngagement::route('/{record}/edit'),
            'view' => ViewWorkerEngagement::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('worker_engagement.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('worker_engagement.create') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('worker_engagement.update') ? Response::allow() : Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('worker_engagement.delete') ? Response::allow() : Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('worker_engagement.delete') ? Response::allow() : Response::deny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
