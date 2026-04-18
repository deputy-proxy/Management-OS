<?php

namespace App\Filament\Resources\WorkshopParticipants;

use App\Models\WorkshopParticipant;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\WorkshopParticipants\Pages;
use App\Filament\Resources\WorkshopParticipants\Schemas\WorkshopParticipantForm;
use App\Filament\Resources\WorkshopParticipants\Schemas\WorkshopParticipantInfolist;
use App\Filament\Resources\WorkshopParticipants\Tables\WorkshopParticipantsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;

class WorkshopParticipantResource extends Resource
{
    protected static ?int $navigationSort = 1;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Workshop Management';
    use HasHexaLite;
    protected static ?string $model = WorkshopParticipant::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    public function defineGates(): array
    {
        return [
            'workshop_participant.index' => __('Allows viewing the workshop participant list'),
            'workshop_participant.create' => __('Allows creating a new workshop participant'),
            'workshop_participant.update' => __('Allows updating workshop participants'),
            'workshop_participant.delete' => __('Allows deleting workshop participants'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return WorkshopParticipantForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkshopParticipantInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkshopParticipantsTable::configure($table);
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
            'index' => Pages\ListWorkshopParticipants::route('/'),
            'create' => Pages\CreateWorkshopParticipant::route('/create'),
            'edit' => Pages\EditWorkshopParticipant::route('/{record}/edit'),
            'view' => Pages\ViewWorkshopParticipant::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_participant.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_participant.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create a workshop participant.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('workshop_participant.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('workshop_participant.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a workshop participant.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('workshop_participant.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete workshop participants.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}


