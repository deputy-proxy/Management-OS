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

class MeetingTopicResource extends Resource
{
    protected static ?string $model = MeetingTopic::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

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
}

