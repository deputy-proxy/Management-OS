<?php

namespace App\Filament\Resources\KpiDefinitions\Tables;

use App\Models\KpiDefinition;
use App\Services\KpiDefinitionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KpiDefinitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('key')->searchable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_until')->date()->placeholder('Open-ended'),
                TextColumn::make('target_value')->placeholder('Not set'),
                TextColumn::make('warning_threshold')->placeholder('Not set'),
                TextColumn::make('critical_threshold')->placeholder('Not set'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview synthetic sample')
                    ->icon('heroicon-o-beaker')
                    ->visible(fn (KpiDefinition $record): bool => $record->status === KpiDefinition::STATUS_DRAFT)
                    ->action(function (KpiDefinition $record): void {
                        $preview = app(KpiDefinitionConfiguration::class)->preview($record->calculation_type, [
                            'filters' => $record->filters ?? [],
                            'dimensions' => $record->dimensions ?? [],
                            'aggregation' => $record->aggregation ?? 'count',
                            'period' => $record->period,
                        ], [
                            ['status' => 'new', 'source_category' => 'organic_search'],
                            ['status' => 'qualified', 'source_category' => 'referral'],
                            ['status' => 'lost', 'source_category' => 'organic_search'],
                        ]);
                        Notification::make()
                            ->title('Synthetic preview: '.$preview['value'].' matching records')
                            ->body('Sample size: '.$preview['sample_size'].'. Synthetic data only; no production records were queried.')
                            ->info()
                            ->send();
                    }),
                EditAction::make()->visible(fn (KpiDefinition $record): bool => $record->status === KpiDefinition::STATUS_DRAFT),
                Action::make('activate')
                    ->label('Approve and activate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (KpiDefinition $record): bool => $record->status === KpiDefinition::STATUS_DRAFT && hexa()->can('kpi_definition.approve'))
                    ->action(fn (KpiDefinition $record): KpiDefinition => app(KpiDefinitionService::class)->activate($record)),
                Action::make('retire')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (KpiDefinition $record): bool => $record->status === KpiDefinition::STATUS_ACTIVE && hexa()->can('kpi_definition.approve'))
                    ->action(fn (KpiDefinition $record): KpiDefinition => app(KpiDefinitionService::class)->retire($record)),
            ])
            ->defaultSort('key');
    }
}
