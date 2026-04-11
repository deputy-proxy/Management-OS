<?php

namespace App\Filament\Resources\FinancialAccountBalances;

use App\Models\FinancialAccountBalance;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\FinancialAccountBalances\Pages;
use App\Filament\Resources\FinancialAccountBalances\Schemas\FinancialAccountBalanceForm;
use App\Filament\Resources\FinancialAccountBalances\Schemas\FinancialAccountBalanceInfolist;
use App\Filament\Resources\FinancialAccountBalances\Tables\FinancialAccountBalancesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FinancialAccountBalanceResource extends Resource
{
    protected static ?string $model = FinancialAccountBalance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return FinancialAccountBalanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FinancialAccountBalanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancialAccountBalancesTable::configure($table);
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
            'index' => Pages\ListFinancialAccountBalances::route('/'),
            'create' => Pages\CreateFinancialAccountBalance::route('/create'),
            'edit' => Pages\EditFinancialAccountBalance::route('/{record}/edit'),
            'view' => Pages\ViewFinancialAccountBalance::route('/{record}'),
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

