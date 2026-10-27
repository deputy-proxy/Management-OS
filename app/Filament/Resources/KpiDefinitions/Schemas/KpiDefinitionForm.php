<?php

namespace App\Filament\Resources\KpiDefinitions\Schemas;

use App\Models\KpiDefinition;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KpiDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('key')
                ->label('Approved KPI key')
                ->options(collect(config('kpi_catalogue.definitions', []))->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['label']])->all())
                ->required()
                ->searchable()
                ->disabled(fn (?KpiDefinition $record): bool => $record !== null),
            DatePicker::make('effective_from')
                ->required()
                ->default(now()->toDateString()),
            TextInput::make('target_value')
                ->numeric()->minValue(0)->step('0.000001'),
            Select::make('threshold_direction')
                ->options([
                    'target_only' => 'Target only, no warning evaluation',
                    'higher_is_worse' => 'Higher values are worse',
                    'lower_is_worse' => 'Lower values are worse',
                ])
                ->required()
                ->default('target_only'),
            TextInput::make('warning_threshold')
                ->numeric()->minValue(0)->step('0.000001'),
            TextInput::make('critical_threshold')
                ->numeric()->minValue(0)->step('0.000001'),
        ]);
    }
}
