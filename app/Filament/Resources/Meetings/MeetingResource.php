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

class MeetingResource extends Resource
{
    protected static ?string $cluster = OperationsCluster::class;
    protected static ?int $navigationSort = 0;
    protected static string|UnitEnum|null $navigationGroup = 'Meetings';
    protected static ?string $model = Meeting::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

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
}

