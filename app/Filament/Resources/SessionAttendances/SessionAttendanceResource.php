<?php

namespace App\Filament\Resources\SessionAttendances;

use App\Models\SessionAttendance;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\SessionAttendances\Pages;
use App\Filament\Resources\SessionAttendances\Schemas\SessionAttendanceForm;
use App\Filament\Resources\SessionAttendances\Schemas\SessionAttendanceInfolist;
use App\Filament\Resources\SessionAttendances\Tables\SessionAttendancesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SessionAttendanceResource extends Resource
{
    protected static ?string $model = SessionAttendance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return SessionAttendanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SessionAttendanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SessionAttendancesTable::configure($table);
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
            'index' => Pages\ListSessionAttendances::route('/'),
            'create' => Pages\CreateSessionAttendance::route('/create'),
            'edit' => Pages\EditSessionAttendance::route('/{record}/edit'),
            'view' => Pages\ViewSessionAttendance::route('/{record}'),
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

