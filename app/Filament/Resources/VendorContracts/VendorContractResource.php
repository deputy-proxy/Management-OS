<?php

namespace App\Filament\Resources\VendorContracts;

use App\Models\VendorContract;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\VendorContracts\Pages;
use App\Filament\Resources\VendorContracts\Schemas\VendorContractForm;
use App\Filament\Resources\VendorContracts\Schemas\VendorContractInfolist;
use App\Filament\Resources\VendorContracts\Tables\VendorContractsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
class VendorContractResource extends Resource
{
    protected static ?string $model = VendorContract::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return VendorContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorContracts::route('/'),
            'create' => Pages\CreateVendorContract::route('/create'),
            'edit' => Pages\EditVendorContract::route('/{record}/edit'),
            'view' => Pages\ViewVendorContract::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

