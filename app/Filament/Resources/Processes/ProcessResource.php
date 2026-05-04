<?php

namespace App\Filament\Resources\Processes;

use App\Models\Process;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Processes\Pages;
use App\Filament\Resources\Processes\Schemas\ProcessForm;
use App\Filament\Resources\Processes\Schemas\ProcessInfolist;
use App\Filament\Resources\Processes\Tables\ProcessesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;
class ProcessResource extends Resource
{
    protected static ?int $navigationSort = 1;
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Compliance Overview';
    use HasHexaLite;

    protected static ?string $model = Process::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    public function defineGates(): array
    {
        return [ActivitiesRelationManager::class, 'process.index' => __('Allows viewing the process list'), 'process.create' => __('Allows creating a new process'), 'process.update' => __('Allows updating processs'), 'process.delete' => __('Allows deleting processs')];
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Number of defined processes';
    }

    public static function form(Schema $schema): Schema
    {
        return ProcessForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProcessInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcesses::route('/'),
            'create' => Pages\CreateProcess::route('/create'),
            'edit' => Pages\EditProcess::route('/{record}/edit'),
            'view' => Pages\ViewProcess::route('/{record}')
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('process.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('process.create') ? Response::allow() : Response::deny(__('You do not have permission to create process.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('process.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('process.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a process.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('process.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete processs.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
