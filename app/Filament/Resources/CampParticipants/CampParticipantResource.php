<?php

namespace App\Filament\Resources\CampParticipants;

use App\Models\CampParticipant;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CampParticipants\Pages;
use App\Filament\Resources\CampParticipants\Schemas\CampParticipantForm;
use App\Filament\Resources\CampParticipants\Schemas\CampParticipantInfolist;
use App\Filament\Resources\CampParticipants\Tables\CampParticipantsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class CampParticipantResource extends Resource
{
    protected static ?int $navigationSort = 2;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Camp Management';
    protected static ?string $model = CampParticipant::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    
    use HasHexaLite;
    public static function form(Schema $schema): Schema
    {
        return CampParticipantForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CampParticipantInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampParticipantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampParticipants::route('/'),
            'create' => Pages\CreateCampParticipant::route('/create'),
            'edit' => Pages\EditCampParticipant::route('/{record}/edit'),
            'view' => Pages\ViewCampParticipant::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public function defineGates(): array
    {
        return ['camp_participant.index' => __('Allows viewing the camp participant list'), 'camp_participant.create' => __('Allows creating a new camp participant'), 'camp_participant.update' => __('Allows updating camp participants'), 'camp_participant.delete' => __('Allows deleting camp participants')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('camp_participant.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('camp_participant.create') ? Response::allow() : Response::deny(__('You do not have permission to create camp participant.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('camp_participant.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('camp_participant.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a camp participant.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('camp_participant.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete camp participants.'));
    }
}

