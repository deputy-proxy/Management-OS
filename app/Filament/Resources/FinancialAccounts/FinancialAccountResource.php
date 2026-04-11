<?php

namespace App\Filament\Resources\FinancialAccounts;

use App\Models\FinancialAccount;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\FinancialAccounts\Pages;
use App\Filament\Resources\FinancialAccounts\Schemas\FinancialAccountForm;
use App\Filament\Resources\FinancialAccounts\Schemas\FinancialAccountInfolist;
use App\Filament\Resources\FinancialAccounts\Tables\FinancialAccountsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
class FinancialAccountResource extends Resource
{
    protected static ?string $model = FinancialAccount::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return FinancialAccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FinancialAccountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancialAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinancialAccounts::route('/'),
            'create' => Pages\CreateFinancialAccount::route('/create'),
            'edit' => Pages\EditFinancialAccount::route('/{record}/edit'),
            'view' => Pages\ViewFinancialAccount::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

