<?php

namespace App\Filament\Resources\EmployeePaySlips;

use App\Models\EmployeePaySlip;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeePaySlips\Pages;
use App\Filament\Resources\EmployeePaySlips\Schemas\EmployeePaySlipForm;
use App\Filament\Resources\EmployeePaySlips\Schemas\EmployeePaySlipInfolist;
use App\Filament\Resources\EmployeePaySlips\Tables\EmployeePaySlipsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;
use Hexters\HexaLite\HasHexaLite;
class EmployeePaySlipResource extends Resource
{
    protected static ?int $navigationSort = 6;
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';
    use HasHexaLite;
    protected static ?string $model = EmployeePaySlip::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EmployeePaySlipForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeePaySlipInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeePaySlipsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeePaySlips::route('/'),
            'create' => Pages\CreateEmployeePaySlip::route('/create'),
            'edit' => Pages\EditEmployeePaySlip::route('/{record}/edit'),
            'view' => Pages\ViewEmployeePaySlip::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

