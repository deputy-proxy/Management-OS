<?php

namespace App\Filament\Resources\Budgets;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\Budgets\Schemas\BudgetVersionForm;
use App\Filament\Resources\Budgets\Tables\BudgetsTable;
use App\Models\Budget;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BudgetResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Budget::class;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Budget Management';

    protected static ?string $navigationLabel = 'Budgets';

    protected static ?string $recordTitleAttribute = 'name';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    public function defineGates(): array
    {
        return [
            'budget.index' => __('Allows viewing budgets and version history'),
            'budget.create' => __('Allows creating budget records'),
            'budget.update' => __('Allows editing drafts and creating new versions'),
            'budget.submit' => __('Allows submitting budget versions'),
            'budget.approve' => __('Allows approving submitted budget versions'),
            'budget.retire' => __('Allows retiring approved budget versions'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Budget name')->required()->maxLength(160),
            ...BudgetVersionForm::components(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('latestVersion.lineItems');
    }

    public static function table(Table $table): Table
    {
        return BudgetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBudgets::route('/'),
            'create' => Pages\CreateBudget::route('/create'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('budget.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('budget.create') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny(__('Budget records are versioned; edit a draft or create a new version.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny(__('Budget records and their versions are retained for audit history.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny(__('Budget records and their versions are retained for audit history.'));
    }
}
