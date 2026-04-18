<?php

namespace App\Filament\Resources\MeetingTopcis;

use App\Models\MeetingTopci;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\MeetingTopcis\Pages;
use App\Filament\Resources\MeetingTopcis\Schemas\MeetingTopciForm;
use App\Filament\Resources\MeetingTopcis\Schemas\MeetingTopciInfolist;
use App\Filament\Resources\MeetingTopcis\Tables\MeetingTopcisTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MeetingTopciResource extends Resource
{
    protected static ?string $model = MeetingTopci::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return MeetingTopciForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MeetingTopciInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeetingTopcisTable::configure($table);
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
            'index' => Pages\ListMeetingTopcis::route('/'),
            'create' => Pages\CreateMeetingTopci::route('/create'),
            'edit' => Pages\EditMeetingTopci::route('/{record}/edit'),
            'view' => Pages\ViewMeetingTopci::route('/{record}'),
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

