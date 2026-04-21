<?php

namespace App\Filament\Resources\TestTypes;

use App\Models\TestType;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestTypes\Pages;
use App\Filament\Resources\TestTypes\Schemas\TestTypeForm;
use App\Filament\Resources\TestTypes\Schemas\TestTypeInfolist;
use App\Filament\Resources\TestTypes\Tables\TestTypesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;

class TestTypeResource extends Resource
{
    use HasHexaLite;
    protected static ?string $cluster = AcademicCluster::class;
    protected static ?int $navigationSort = 1;
    protected static string|UnitEnum|null $navigationGroup = 'Tests Management';
    protected static ?string $model = TestType::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TestTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestTypes::route('/'),
            'create' => Pages\CreateTestType::route('/create'),
            'edit' => Pages\EditTestType::route('/{record}/edit'),
            'view' => Pages\ViewTestType::route('/{record}'),
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

