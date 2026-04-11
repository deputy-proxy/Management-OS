<?php

namespace App\Filament\Resources\VendorInvoices;

use App\Models\VendorInvoice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\VendorInvoices\Pages;
use App\Filament\Resources\VendorInvoices\Schemas\VendorInvoiceForm;
use App\Filament\Resources\VendorInvoices\Schemas\VendorInvoiceInfolist;
use App\Filament\Resources\VendorInvoices\Tables\VendorInvoicesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
class VendorInvoiceResource extends Resource
{
    protected static ?string $model = VendorInvoice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return VendorInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorInvoices::route('/'),
            'create' => Pages\CreateVendorInvoice::route('/create'),
            'edit' => Pages\EditVendorInvoice::route('/{record}/edit'),
            'view' => Pages\ViewVendorInvoice::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

