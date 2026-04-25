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

class CampParticipantResource extends Resource
{
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Camp Management';
    protected static ?string $model = CampParticipant::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

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
}

