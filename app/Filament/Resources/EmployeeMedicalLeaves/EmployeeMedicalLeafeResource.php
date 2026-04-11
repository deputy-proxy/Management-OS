<?php

namespace App\Filament\Resources\EmployeeMedicalLeaves;

use App\Models\EmployeeMedicalLeafe;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeMedicalLeaves\Pages;
use App\Filament\Resources\EmployeeMedicalLeaves\Schemas\EmployeeMedicalLeafeForm;
use App\Filament\Resources\EmployeeMedicalLeaves\Schemas\EmployeeMedicalLeafeInfolist;
use App\Filament\Resources\EmployeeMedicalLeaves\Tables\EmployeeMedicalLeavesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;

class EmployeeMedicalLeafeResource extends Resource
{
    protected static ?string $model = EmployeeMedicalLeafe::class;
    protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EmployeeMedicalLeafeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeMedicalLeafeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeMedicalLeavesTable::configure($table);
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
            'index' => Pages\ListEmployeeMedicalLeaves::route('/'),
            'create' => Pages\CreateEmployeeMedicalLeafe::route('/create'),
            'edit' => Pages\EditEmployeeMedicalLeafe::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeMedicalLeafe::route('/{record}'),
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

