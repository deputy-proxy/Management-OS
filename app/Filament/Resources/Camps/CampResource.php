<?php

namespace App\Filament\Resources\Camps;

use App\Models\Camp;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Camps\Pages;
use App\Filament\Resources\Camps\Schemas\CampForm;
use App\Filament\Resources\Camps\Schemas\CampInfolist;
use App\Filament\Resources\Camps\Tables\CampsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Portfolio\PortfolioCluster;
class CampResource extends Resource
{
    protected static ?int $navigationSort = 6;
    protected static ?string $cluster = PortfolioCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Camp Management';
    protected static ?string $model = Camp::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CampForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CampInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCamps::route('/'),
            'create' => Pages\CreateCamp::route('/create'),
            'edit' => Pages\EditCamp::route('/{record}/edit'),
            'view' => Pages\ViewCamp::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

