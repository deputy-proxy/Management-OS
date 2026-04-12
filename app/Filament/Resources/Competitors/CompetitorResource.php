<?php

namespace App\Filament\Resources\Competitors;

use App\Models\Competitor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Competitors\Pages;
use App\Filament\Resources\Competitors\Schemas\CompetitorForm;
use App\Filament\Resources\Competitors\Schemas\CompetitorInfolist;
use App\Filament\Resources\Competitors\Tables\CompetitorsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompetitorResource extends Resource
{
    protected static ?string $model = Competitor::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return CompetitorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompetitorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitorsTable::configure($table);
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
            'index' => Pages\ListCompetitors::route('/'),
            'create' => Pages\CreateCompetitor::route('/create'),
            'edit' => Pages\EditCompetitor::route('/{record}/edit'),
            'view' => Pages\ViewCompetitor::route('/{record}'),
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

