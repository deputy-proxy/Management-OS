<?php

namespace App\Filament\Resources\EmployeePaySlips\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeePaySlipInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('employee.full_name')->label('Employee'),
            TextEntry::make('month')->date('F Y')->label('Pay Month'),
            TextEntry::make('status'),
            TextEntry::make('manual_amount')->money('RON')->label('Manual Baseline'),
            TextEntry::make('amount')->money('RON')->label('Calculated Total'),
            TextEntry::make('due_on')->date(),
            TextEntry::make('paid_on')->date(),
            Section::make('Source-linked payroll items')
                ->description('Each line stores the source ID, rate and calculation snapshot used when it was added. These records are immutable.')
                ->schema([
                    RepeatableEntry::make('lineItems')->schema([
                        TextEntry::make('occurred_on')->date()->label('Date'),
                        TextEntry::make('description'),
                        TextEntry::make('quantity')->label('Quantity'),
                        TextEntry::make('unit'),
                        TextEntry::make('unit_rate')->money('RON')->label('Rate'),
                        TextEntry::make('amount')->money('RON'),
                        TextEntry::make('source_type')->label('Source type'),
                        TextEntry::make('source_id')->label('Source ID'),
                    ])->columns(4),
                ]),
            Section::make('Legacy payroll breakdown')
                ->description('Existing JSON breakdown retained for historical compatibility.')
                ->schema([TextEntry::make('items')->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]')->columnSpanFull()]),
        ]);
    }
}
