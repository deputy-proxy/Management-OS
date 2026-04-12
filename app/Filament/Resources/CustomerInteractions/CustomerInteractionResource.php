<?php

namespace App\Filament\Resources\CustomerInteractions;

use App\Models\CustomerInteraction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CustomerInteractions\Pages;
use App\Filament\Resources\CustomerInteractions\Schemas\CustomerInteractionForm;
use App\Filament\Resources\CustomerInteractions\Schemas\CustomerInteractionInfolist;
use App\Filament\Resources\CustomerInteractions\Tables\CustomerInteractionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
class CustomerInteractionResource extends Resource
{
    protected static ?string $model = CustomerInteraction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CustomerInteractionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInteractionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerInteractionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerInteractions::route('/'),
            'create' => Pages\CreateCustomerInteraction::route('/create'),
            'edit' => Pages\EditCustomerInteraction::route('/{record}/edit'),
            'view' => Pages\ViewCustomerInteraction::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

