<?php

namespace App\Filament\Resources\EmployeeEvaluationForms;

use App\Models\EmployeeEvaluationForm;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\EmployeeEvaluationForms\Pages;
use App\Filament\Resources\EmployeeEvaluationForms\Schemas\EmployeeEvaluationFormForm;
use App\Filament\Resources\EmployeeEvaluationForms\Schemas\EmployeeEvaluationFormInfolist;
use App\Filament\Resources\EmployeeEvaluationForms\Tables\EmployeeEvaluationFormsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
class EmployeeEvaluationFormResource extends Resource
{
    protected static ?string $model = EmployeeEvaluationForm::class;
    //protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return EmployeeEvaluationFormForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeEvaluationFormInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeEvaluationFormsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeEvaluationForms::route('/'),
            'create' => Pages\CreateEmployeeEvaluationForm::route('/create'),
            'edit' => Pages\EditEmployeeEvaluationForm::route('/{record}/edit'),
            'view' => Pages\ViewEmployeeEvaluationForm::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

