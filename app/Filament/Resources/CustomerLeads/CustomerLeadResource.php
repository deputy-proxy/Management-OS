<?php

namespace App\Filament\Resources\CustomerLeads;

use App\Models\CustomerLead;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CustomerLeads\Pages;
use App\Filament\Resources\CustomerLeads\Schemas\CustomerLeadForm;
use App\Filament\Resources\CustomerLeads\Schemas\CustomerLeadInfolist;
use App\Filament\Resources\CustomerLeads\Tables\CustomerLeadsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerLeadResource extends Resource
{
    protected static ?string $model = CustomerLead::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CustomerLeadForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerLeadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerLeadsTable::configure($table);
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
            'index' => Pages\ListCustomerLeads::route('/'),
            'create' => Pages\CreateCustomerLead::route('/create'),
            'edit' => Pages\EditCustomerLead::route('/{record}/edit'),
            'view' => Pages\ViewCustomerLead::route('/{record}'),
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

