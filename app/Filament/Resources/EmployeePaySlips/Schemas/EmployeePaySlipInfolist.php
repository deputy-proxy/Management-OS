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
            Section::make('Versioned payroll calculation snapshots')
                ->description('Snapshots are immutable. A ready status means inputs are present, not that statutory payroll has been calculated or approved.')
                ->schema([
                    RepeatableEntry::make('calculationRuns')->schema([
                        TextEntry::make('id')->label('Run ID'),
                        TextEntry::make('run_status')->badge()->label('Run status'),
                        TextEntry::make('period_start')->date(),
                        TextEntry::make('period_end')->date(),
                        TextEntry::make('policy_snapshot.policy_key')->label('Policy key'),
                        TextEntry::make('policy_snapshot.version')->label('Policy version'),
                        TextEntry::make('blocked_reasons')->listWithLineBreaks(),
                        TextEntry::make('result.result_status')->label('Result snapshot status'),
                        TextEntry::make('result.snapshot_hash')->label('Snapshot SHA-256'),
                    ])->columns(4),
                ]),
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
