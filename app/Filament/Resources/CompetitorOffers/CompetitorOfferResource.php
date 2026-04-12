<?php

namespace App\Filament\Resources\CompetitorOffers;

use App\Models\CompetitorOffer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\CompetitorOffers\Pages;
use App\Filament\Resources\CompetitorOffers\Schemas\CompetitorOfferForm;
use App\Filament\Resources\CompetitorOffers\Schemas\CompetitorOfferInfolist;
use App\Filament\Resources\CompetitorOffers\Tables\CompetitorOffersTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompetitorOfferResource extends Resource
{
    protected static ?string $model = CompetitorOffer::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CompetitorOfferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompetitorOfferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitorOffersTable::configure($table);
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
            'index' => Pages\ListCompetitorOffers::route('/'),
            'create' => Pages\CreateCompetitorOffer::route('/create'),
            'edit' => Pages\EditCompetitorOffer::route('/{record}/edit'),
            'view' => Pages\ViewCompetitorOffer::route('/{record}'),
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

