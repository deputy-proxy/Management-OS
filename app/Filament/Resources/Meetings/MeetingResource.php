<?php

namespace App\Filament\Resources\Meetings;

use App\Models\Meeting;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Meetings\Pages;
use App\Filament\Resources\Meetings\Schemas\MeetingForm;
use App\Filament\Resources\Meetings\Schemas\MeetingInfolist;
use App\Filament\Resources\Meetings\Tables\MeetingsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Operations\OperationsCluster;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class MeetingResource extends Resource
{
    protected static ?int $navigationSort = 1;
    protected static ?string $cluster = OperationsCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Meetings';
    protected static ?string $model = Meeting::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    use HasHexaLite;

    public static function form(Schema $schema): Schema
    {
        return MeetingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MeetingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeetingsTable::configure($table);
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
            'index' => Pages\ListMeetings::route('/'),
            'create' => Pages\CreateMeeting::route('/create'),
            'edit' => Pages\EditMeeting::route('/{record}/edit'),
            'view' => Pages\ViewMeeting::route('/{record}'),
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
        return ['meeting.index' => __('Allows viewing the meeting list'), 'meeting.create' => __('Allows creating a new meeting'), 'meeting.update' => __('Allows updating meetings'), 'meeting.delete' => __('Allows deleting meetings')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('meeting.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('meeting.create') ? Response::allow() : Response::deny(__('You do not have permission to create meeting.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('meeting.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('meeting.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a meeting.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('meeting.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete meetings.'));
    }
}

