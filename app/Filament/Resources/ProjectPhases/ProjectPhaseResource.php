<?php

namespace App\Filament\Resources\ProjectPhases;

use App\Models\ProjectPhase;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ProjectPhases\Pages;
use App\Filament\Resources\ProjectPhases\Schemas\ProjectPhaseForm;
use App\Filament\Resources\ProjectPhases\Schemas\ProjectPhaseInfolist;
use App\Filament\Resources\ProjectPhases\Tables\ProjectPhasesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectPhaseResource extends Resource
{
    protected static ?string $model = ProjectPhase::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ProjectPhaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProjectPhaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectPhasesTable::configure($table);
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
            'index' => Pages\ListProjectPhases::route('/'),
            'create' => Pages\CreateProjectPhase::route('/create'),
            'edit' => Pages\EditProjectPhase::route('/{record}/edit'),
            'view' => Pages\ViewProjectPhase::route('/{record}'),
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

