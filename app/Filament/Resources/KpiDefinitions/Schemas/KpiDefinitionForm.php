<?php

namespace App\Filament\Resources\KpiDefinitions\Schemas;

use App\Models\KpiDefinition;
use App\Models\User;
use App\Services\KpiCatalogueRegistry;
use App\Services\KpiDefinitionConfiguration;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class KpiDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('key')
                ->label('Registered KPI key')
                ->options(app(KpiCatalogueRegistry::class)->options())
                ->required()
                ->searchable()
                ->live()
                ->disabled(fn (?KpiDefinition $record): bool => $record !== null),
            DatePicker::make('effective_from')
                ->required()
                ->default(now()->toDateString()),
            Repeater::make('filters')
                ->schema([
                    Select::make('field')
                        ->options(fn (Get $get): array => self::configurationOptions($get))
                        ->required(),
                    Select::make('operator')
                        ->options(['eq' => 'Equals', 'neq' => 'Does not equal'])
                        ->default('eq')
                        ->required(),
                    TextInput::make('value')
                        ->label('Allowed value')
                        ->required()
                        ->maxLength(80),
                ])
                ->columns(3)
                ->defaultItems(0)
                ->maxItems(KpiDefinitionConfiguration::MAX_FILTERS)
                ->helperText('Filters are restricted to registered fields and controlled values for the selected KPI.'),
            Select::make('dimensions')
                ->multiple()
                ->options(fn (Get $get): array => self::configurationOptions($get))
                ->maxItems(KpiDefinitionConfiguration::MAX_DIMENSIONS)
                ->helperText('Optional grouping dimensions. Only dimensions registered for the selected KPI are accepted.'),
            Select::make('aggregation')
                ->options(['count' => 'Count records'])
                ->default('count')
                ->required(),
            Select::make('period.granularity')
                ->label('Reporting period')
                ->options(['day' => 'Daily as-of'])
                ->default('day')
                ->disabled(),
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
            Toggle::make('alerts_enabled')
                ->label('Enable KPI threshold alerts')
                ->default(false)
                ->helperText('Threshold rules remain provisional. Notification delivery is disabled until separately approved and configured.'),
            TextInput::make('alert_cooldown_minutes')
                ->numeric()->integer()->minValue(1)->maxValue(10080)->default(60)
                ->label('Alert cooldown (minutes)'),
            Select::make('alert_owner_id')
                ->label('Alert owner')
                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            Textarea::make('change_summary')
                ->label('Change summary')
                ->required()
                ->maxLength(500)
                ->rows(2)
                ->helperText('Recorded in the immutable KPI audit history.'),
        ]);
    }

    /** @return array<string, string> */
    private static function configurationOptions(Get $get): array
    {
        $key = $get('../../key') ?? $get('../key') ?? $get('key');
        $definition = is_string($key) ? app(KpiCatalogueRegistry::class)->get($key) : null;
        if (! is_array($definition)) {
            return [];
        }

        $options = [];
        foreach (app(KpiDefinitionConfiguration::class)->dimensions($definition['calculation_type']) as $field) {
            $options[$field] = match ($field) {
                'status' => 'Lead status',
                'source_category' => 'Lead source category',
                default => $field,
            };
        }

        return $options;
    }
}
