<?php

namespace App\Filament\Resources\SessionAssistances;

use App\Models\SessionAssistance;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\SessionAssistances\Pages;
use App\Filament\Resources\SessionAssistances\Schemas\SessionAssistanceForm;
use App\Filament\Resources\SessionAssistances\Schemas\SessionAssistanceInfolist;
use App\Filament\Resources\SessionAssistances\Tables\SessionAssistancesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SessionAssistanceResource extends Resource
{
    protected static ?string $model = SessionAssistance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return SessionAssistanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SessionAssistanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SessionAssistancesTable::configure($table);
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
            'index' => Pages\ListSessionAssistances::route('/'),
            'create' => Pages\CreateSessionAssistance::route('/create'),
            'edit' => Pages\EditSessionAssistance::route('/{record}/edit'),
            'view' => Pages\ViewSessionAssistance::route('/{record}'),
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

