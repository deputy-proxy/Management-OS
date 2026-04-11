<?php

namespace App\Filament\Resources\EmployeeHolidays;

use App\Models\EmployeeHoliday;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeHolidays\Pages;
use App\Filament\Resources\EmployeeHolidays\Schemas\EmployeeHolidayForm;
use App\Filament\Resources\EmployeeHolidays\Schemas\EmployeeHolidayInfolist;
use App\Filament\Resources\EmployeeHolidays\Tables\EmployeeHolidaysTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;

class EmployeeHolidayResource extends Resource
{
    protected static ?string $model = EmployeeHoliday::class;
    protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EmployeeHolidayForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeHolidayInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeHolidaysTable::configure($table);
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
            'index' => Pages\ListEmployeeHolidays::route('/'),
            'create' => Pages\CreateEmployeeHoliday::route('/create'),
            'edit' => Pages\EditEmployeeHoliday::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeHoliday::route('/{record}'),
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

