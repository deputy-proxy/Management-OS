<?php

namespace App\Filament\Resources\EmployeeTimesheets;

use App\Models\EmployeeTimesheet;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeTimesheets\Pages;
use App\Filament\Resources\EmployeeTimesheets\Schemas\EmployeeTimesheetForm;
use App\Filament\Resources\EmployeeTimesheets\Schemas\EmployeeTimesheetInfolist;
use App\Filament\Resources\EmployeeTimesheets\Tables\EmployeeTimesheetsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
class EmployeeTimesheetResource extends Resource
{
    protected static ?string $model = EmployeeTimesheet::class;

    protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EmployeeTimesheetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeTimesheetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeTimesheetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeTimesheets::route('/'),
            'create' => Pages\CreateEmployeeTimesheet::route('/create'),
            'edit' => Pages\EditEmployeeTimesheet::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeTimesheet::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

