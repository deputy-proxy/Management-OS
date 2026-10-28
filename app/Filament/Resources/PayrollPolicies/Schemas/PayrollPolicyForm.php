<?php

namespace App\Filament\Resources\PayrollPolicies\Schemas;

use App\Models\PayrollPolicy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Policy identity and effective dates')
                ->description('Policies are versioned configuration. Unconfigured statutory parameters remain gaps and block dependent calculations.')
                ->schema([
                    TextInput::make('policy_key')
                        ->helperText('Stable lowercase key, for example ro.employee.payroll. Do not encode a rate or year in the key.')
                        ->required()->maxLength(100)->regex('/^[a-z][a-z0-9_.-]*$/')
                        ->disabled(fn (?PayrollPolicy $record): bool => $record !== null),
                    TextInput::make('name')->required()->maxLength(255),
                    DatePicker::make('effective_from')->required(),
                    DatePicker::make('effective_until')->afterOrEqual('effective_from'),
                    Textarea::make('description')->rows(2)->maxLength(2000),
                ])->columns(2),
            Section::make('Calculation configuration')
                ->description('Parameters are data only. This configuration does not execute arbitrary expressions or supply defaults for missing statutory values.')
                ->schema([
                    KeyValue::make('parameters')
                        ->keyLabel('Parameter path')
                        ->valueLabel('Configured value')
                        ->addActionLabel('Add parameter')
                        ->default([])
                        ->columnSpanFull(),
                    TagsInput::make('required_parameters')
                        ->label('Required parameter paths')
                        ->helperText('Nested paths are supported, for example contribution.cas_rate. Missing values are shown as configuration gaps.'),
                    TagsInput::make('calculation_order')
                        ->helperText('Each step must match a configured parameter key or dotted parameter path. These are identifiers, not executable code.'),
                    Select::make('rounding_mode')->options([
                        'half_up' => 'Half up',
                        'half_down' => 'Half down',
                        'half_even' => 'Half even',
                        'half_odd' => 'Half odd',
                    ])->native(false),
                    TextInput::make('rounding_precision')->numeric()->integer()->minValue(0)->maxValue(6),
                ])->columns(2),
            Section::make('Notes')->schema([
                Textarea::make('notes')->rows(3)->maxLength(5000)->columnSpanFull(),
            ]),
        ]);
    }
}
