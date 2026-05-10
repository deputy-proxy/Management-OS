<?php

namespace App\Filament\Resources\IndividualSessions;

use App\Models\IndividualSession;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\IndividualSessions\Pages;
use App\Filament\Resources\IndividualSessions\Schemas\IndividualSessionForm;
use App\Filament\Resources\IndividualSessions\Schemas\IndividualSessionInfolist;
use App\Filament\Resources\IndividualSessions\Tables\IndividualSessionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
class IndividualSessionResource extends Resource
{
    protected static ?int $navigationSort = 5;
    protected static ?string $cluster = AcademicCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Students Management';
    protected static ?string $model = IndividualSession::class;
    use HasHexaLite;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return IndividualSessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IndividualSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndividualSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndividualSessions::route('/'),
            'create' => Pages\CreateIndividualSession::route('/create'),
            'edit' => Pages\EditIndividualSession::route('/{record}/edit'),
            'view' => Pages\ViewIndividualSession::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public function defineGates(): array
    {
        return ['individual_session.index' => __('Allows viewing the individual session list'), 'individual_session.create' => __('Allows creating a new individual session'), 'individual_session.update' => __('Allows updating individual sessions'), 'individual_session.delete' => __('Allows deleting individual sessions')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('individual_session.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('individual_session.create') ? Response::allow() : Response::deny(__('You do not have permission to create individual session.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('individual_session.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('individual_session.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete an individual session.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('individual_session.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete individual sessions.'));
    }
}

