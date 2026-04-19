<?php

namespace App\Filament\Resources\ProgressUpdates;

use App\Models\ProgressUpdate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProgressUpdates\Pages;
use App\Filament\Resources\ProgressUpdates\Schemas\ProgressUpdateForm;
use App\Filament\Resources\ProgressUpdates\Schemas\ProgressUpdateInfolist;
use App\Filament\Resources\ProgressUpdates\Tables\ProgressUpdatesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;

class ProgressUpdateResource extends Resource
{
    protected static ?int $navigationSort = 3;
    protected static ?string $cluster = AcademicCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Students Management';
    use HasHexaLite;

    protected static ?string $model = ProgressUpdate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'progress_update.index' => __('Allows viewing the progress update list'),
            'progress_update.create' => __('Allows creating a new progress update'),
            'progress_update.update' => __('Allows updating progress updates'),
            'progress_update.delete' => __('Allows deleting progress updates'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ProgressUpdateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProgressUpdateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgressUpdatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgressUpdates::route('/'),
            'create' => Pages\CreateProgressUpdate::route('/create'),
            'edit' => Pages\EditProgressUpdate::route('/{record}/edit'),
            'view' => Pages\ViewProgressUpdate::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('progress_update.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('progress_update.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create progress update.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('progress_update.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('progress_update.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a progress update.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('progress_update.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete progress updates.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
