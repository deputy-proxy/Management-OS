<?php

namespace App\Filament\Resources\PotentialPartners;

use App\Models\PotentialPartner;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\PotentialPartners\Pages;
use App\Filament\Resources\PotentialPartners\Schemas\PotentialPartnerForm;
use App\Filament\Resources\PotentialPartners\Schemas\PotentialPartnerInfolist;
use App\Filament\Resources\PotentialPartners\Tables\PotentialPartnersTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PotentialPartnerResource extends Resource
{
    protected static ?string $model = PotentialPartner::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return PotentialPartnerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PotentialPartnerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PotentialPartnersTable::configure($table);
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
            'index' => Pages\ListPotentialPartners::route('/'),
            'create' => Pages\CreatePotentialPartner::route('/create'),
            'edit' => Pages\EditPotentialPartner::route('/{record}/edit'),
            'view' => Pages\ViewPotentialPartner::route('/{record}'),
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

