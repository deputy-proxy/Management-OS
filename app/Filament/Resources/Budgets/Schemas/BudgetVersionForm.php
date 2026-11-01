<?php

namespace App\Filament\Resources\Budgets\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class BudgetVersionForm
{
    /** @return array<int, Component> */
    public static function components(): array
    {
        return [
            DatePicker::make('period_start')->label('Period start')->required(),
            DatePicker::make('period_end')->label('Period end')->required()->afterOrEqual('period_start'),
            TextInput::make('currency_code')->label('Currency (ISO 4217)')->required()->minLength(3)->maxLength(3)->default('RON')->dehydrateStateUsing(fn ($state): string => strtoupper((string) $state)),
            KeyValue::make('dimensions')->keyLabel('Dimension')->valueLabel('Value')->addActionLabel('Add dimension')->columnSpanFull(),
            Repeater::make('line_items')->label('Budget line items')->schema([
                TextInput::make('code')->maxLength(64),
                TextInput::make('description')->required()->maxLength(200)->columnSpan(2),
                TextInput::make('amount')->numeric()->minValue(0)->step('0.01')->required(),
                KeyValue::make('dimensions')->keyLabel('Dimension')->valueLabel('Value')->columnSpanFull(),
            ])->columns(3)->minItems(1)->defaultItems(1)->columnSpanFull(),
            TextInput::make('change_summary')->label('Version change summary')->maxLength(500)->columnSpanFull(),
        ];
    }
}
