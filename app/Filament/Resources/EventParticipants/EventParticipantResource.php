<?php

namespace App\Filament\Resources\EventParticipants;

use App\Models\EventParticipant;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EventParticipants\Pages;
use App\Filament\Resources\EventParticipants\Schemas\EventParticipantForm;
use App\Filament\Resources\EventParticipants\Schemas\EventParticipantInfolist;
use App\Filament\Resources\EventParticipants\Tables\EventParticipantsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;

class EventParticipantResource extends Resource
{
    protected static ?int $navigationSort = 17;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Event Management';
    use HasHexaLite;
    protected static ?string $model = EventParticipant::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    
    public function defineGates(): array
    {
        return [
            'event_participant.index' => __('Allows viewing the event participant list'),
            'event_participant.create' => __('Allows creating a new event participant'),
            'event_participant.update' => __('Allows updating event participants'),
            'event_participant.delete' => __('Allows deleting event participants'),
        ];
    }
    public static function form(Schema $schema): Schema
    {
        return EventParticipantForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventParticipantInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventParticipantsTable::configure($table);
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
            'index' => Pages\ListEventParticipants::route('/'),
            'create' => Pages\CreateEventParticipant::route('/create'),
            'edit' => Pages\EditEventParticipant::route('/{record}/edit'),
            'view' => Pages\ViewEventParticipant::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('event_participant.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('event_participant.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create an event participant.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('event_participant.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('event_participant.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete an event participant.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('event_participant.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete event participants.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}


