<?php

namespace App\Filament\Resources\CustomerContracts;

use App\Models\CustomerContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CustomerContracts\Pages;
use App\Filament\Resources\CustomerContracts\Schemas\CustomerContractForm;
use App\Filament\Resources\CustomerContracts\Schemas\CustomerContractInfolist;
use App\Filament\Resources\CustomerContracts\Tables\CustomerContractsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerContractResource extends Resource
{
    protected static ?string $model = CustomerContract::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CustomerContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerContractsTable::configure($table);
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
            'index' => Pages\ListCustomerContracts::route('/'),
            'create' => Pages\CreateCustomerContract::route('/create'),
            'edit' => Pages\EditCustomerContract::route('/{record}/edit'),
            'view' => Pages\ViewCustomerContract::route('/{record}'),
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

