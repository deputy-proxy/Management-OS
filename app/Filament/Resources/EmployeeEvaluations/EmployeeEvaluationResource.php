<?php

namespace App\Filament\Resources\EmployeeEvaluations;

use App\Models\EmployeeEvaluation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeEvaluations\Pages;
use App\Filament\Resources\EmployeeEvaluations\Schemas\EmployeeEvaluationForm;
use App\Filament\Resources\EmployeeEvaluations\Schemas\EmployeeEvaluationInfolist;
use App\Filament\Resources\EmployeeEvaluations\Tables\EmployeeEvaluationsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;

class EmployeeEvaluationResource extends Resource
{
    protected static ?string $model = EmployeeEvaluation::class;
    //protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EmployeeEvaluationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeEvaluationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeEvaluationsTable::configure($table);
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
            'index' => Pages\ListEmployeeEvaluations::route('/'),
            'create' => Pages\CreateEmployeeEvaluation::route('/create'),
            'edit' => Pages\EditEmployeeEvaluation::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeEvaluation::route('/{record}'),
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

