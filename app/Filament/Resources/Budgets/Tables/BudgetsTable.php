<?php

namespace App\Filament\Resources\Budgets\Tables;

use App\Filament\Pages\BudgetActualsReport;
use App\Filament\Resources\Budgets\Schemas\BudgetVersionForm;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Services\BudgetVersionService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Budget')->searchable()->sortable(),
            TextColumn::make('latestVersion.version')->label('Version')->formatStateUsing(fn ($state): string => $state ? 'v'.$state : '—'),
            TextColumn::make('latestVersion.status')->label('Status')->badge(),
            TextColumn::make('latestVersion.period_start')->label('From')->date(),
            TextColumn::make('latestVersion.period_end')->label('Through')->date(),
            TextColumn::make('latestVersion.currency_code')->label('Currency'),
            TextColumn::make('latestVersion.lineItems_count')->label('Lines')->state(fn (Budget $record): int => $record->latestVersion?->lineItems->count() ?? 0),
            TextColumn::make('created_at')->dateTime()->since(),
        ])->recordActions([
            Action::make('actuals_comparison')
                ->label('Budget vs actual')
                ->icon('heroicon-o-scale')
                ->url(fn (Budget $record): string => BudgetActualsReport::getUrl([
                    'budget' => $record->getKey(),
                    'version' => $record->latestVersion?->getKey(),
                ]))
                ->visible(fn (Budget $record): bool => $record->latestVersion !== null && hexa()->can('budget.actuals.view')),
            Action::make('edit_draft')
                ->label('Edit draft')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (Budget $record): bool => $record->latestVersion?->status === BudgetVersion::STATUS_DRAFT && hexa()->can('budget.update'))
                ->fillForm(fn (Budget $record): array => self::versionFormData($record->latestVersion))
                ->form(BudgetVersionForm::components())
                ->action(fn (Budget $record, array $data, BudgetVersionService $service) => $service->updateDraft($record->latestVersion, $data)),
            Action::make('new_version')
                ->label('New version')
                ->icon('heroicon-o-document-plus')
                ->visible(fn (Budget $record): bool => in_array($record->latestVersion?->status, [BudgetVersion::STATUS_APPROVED, BudgetVersion::STATUS_RETIRED], true) && hexa()->can('budget.update'))
                ->fillForm(fn (Budget $record): array => array_replace(self::versionFormData($record->latestVersion), ['change_summary' => null]))
                ->form(BudgetVersionForm::components())
                ->action(fn (Budget $record, array $data, BudgetVersionService $service) => $service->createVersion($record, $data)),
            Action::make('submit')
                ->label('Submit')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (Budget $record): bool => $record->latestVersion?->status === BudgetVersion::STATUS_DRAFT && hexa()->can('budget.submit'))
                ->action(fn (Budget $record, BudgetVersionService $service) => $service->submit($record->latestVersion)),
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Budget $record): bool => $record->latestVersion?->status === BudgetVersion::STATUS_SUBMITTED
                    && hexa()->can('budget.approve')
                    && (! $record->versions()->where('status', BudgetVersion::STATUS_APPROVED)->exists() || hexa()->can('budget.retire')))
                ->action(fn (Budget $record, BudgetVersionService $service) => $service->approve($record->latestVersion)),
            Action::make('retire')
                ->label('Retire')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (Budget $record): bool => $record->latestVersion?->status === BudgetVersion::STATUS_APPROVED && hexa()->can('budget.retire'))
                ->action(fn (Budget $record, BudgetVersionService $service) => $service->retire($record->latestVersion)),
            Action::make('history')
                ->label('Version history')
                ->icon('heroicon-o-clock')
                ->modalHeading(fn (Budget $record): string => $record->name.' version history')
                ->modalSubmitAction(false)
                ->modalContent(fn (Budget $record) => view('filament.resources.budgets.history', [
                    'budget' => $record->load('versions.lineItems', 'versions.events.actor'),
                ])),
        ])->defaultSort('created_at', 'desc');
    }

    /** @return array<string, mixed> */
    private static function versionFormData(?BudgetVersion $version): array
    {
        if (! $version) {
            return [];
        }

        return [
            'period_start' => $version->period_start,
            'period_end' => $version->period_end,
            'currency_code' => $version->currency_code,
            'dimensions' => $version->dimensions ?? [],
            'line_items' => $version->lineItems->map(fn ($item): array => [
                'code' => $item->code,
                'description' => $item->description,
                'amount' => $item->amount,
                'dimensions' => $item->dimensions ?? [],
            ])->all(),
            'change_summary' => $version->change_summary,
        ];
    }
}