<?php

namespace App\Filament\Resources\EmployeeContracts;

use App\Models\EmployeeContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeContracts\Pages;
use App\Filament\Resources\EmployeeContracts\Schemas\EmployeeContractForm;
use App\Filament\Resources\EmployeeContracts\Schemas\EmployeeContractInfolist;
use App\Filament\Resources\EmployeeContracts\Tables\EmployeeContractsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;

class EmployeeContractResource extends Resource
{
    protected static ?string $model = EmployeeContract::class;
    //protected static ?string $cluster = HumanResourcesCluster::class;
    protected static ?int $navigationSort = 3;


    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EmployeeContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeContractsTable::configure($table);
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
            'index' => Pages\ListEmployeeContracts::route('/'),
            'create' => Pages\CreateEmployeeContract::route('/create'),
            'edit' => Pages\EditEmployeeContract::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeContract::route('/{record}'),
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

