<?php

namespace App\Filament\Resources\MeetingTopics;

use App\Models\MeetingTopic;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\MeetingTopics\Pages;
use App\Filament\Resources\MeetingTopics\Schemas\MeetingTopicForm;
use App\Filament\Resources\MeetingTopics\Schemas\MeetingTopicInfolist;
use App\Filament\Resources\MeetingTopics\Tables\MeetingTopicsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Operations\OperationsCluster;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;

class MeetingTopicResource extends Resource
{
    protected static ?int $navigationSort = 2;
    protected static ?string $cluster = OperationsCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Meetings';
    protected static ?string $model = MeetingTopic::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    use HasHexaLite;
    public static function form(Schema $schema): Schema
    {
        return MeetingTopicForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MeetingTopicInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeetingTopicsTable::configure($table);
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
            'index' => Pages\ListMeetingTopics::route('/'),
            'create' => Pages\CreateMeetingTopic::route('/create'),
            'edit' => Pages\EditMeetingTopic::route('/{record}/edit'),
            'view' => Pages\ViewMeetingTopic::route('/{record}'),
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
        return ['meeting_topic.index' => __('Allows viewing the meeting topic list'), 'meeting_topic.create' => __('Allows creating a new meeting topic'), 'meeting_topic.update' => __('Allows updating meeting topics'), 'meeting_topic.delete' => __('Allows deleting meeting topics')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('meeting_topic.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('meeting_topic.create') ? Response::allow() : Response::deny(__('You do not have permission to create meeting topic.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('meeting_topic.update') ? Response::allow() : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('meeting_topic.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete a meeting topic.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('meeting_topic.delete') ? Response::allow() : Response::deny(__('You do not have permission to delete meeting topics.'));
    }
}

