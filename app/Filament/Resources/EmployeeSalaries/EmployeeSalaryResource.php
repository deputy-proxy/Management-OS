<?php

namespace App\Filament\Resources\EmployeeSalaries;

use App\Models\EmployeeSalary;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeSalaries\Pages;
use App\Filament\Resources\EmployeeSalaries\Schemas\EmployeeSalaryForm;
use App\Filament\Resources\EmployeeSalaries\Schemas\EmployeeSalaryInfolist;
use App\Filament\Resources\EmployeeSalaries\Tables\EmployeeSalariesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;

class EmployeeSalaryResource extends Resource
{
    protected static ?string $model = EmployeeSalary::class;
    protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EmployeeSalaryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeSalaryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeSalariesTable::configure($table);
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
            'index' => Pages\ListEmployeeSalaries::route('/'),
            'create' => Pages\CreateEmployeeSalary::route('/create'),
            'edit' => Pages\EditEmployeeSalary::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeSalary::route('/{record}'),
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

