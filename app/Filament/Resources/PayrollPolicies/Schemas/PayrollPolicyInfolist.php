<?php

namespace App\Filament\Resources\PayrollPolicies\Schemas;

use App\Models\PayrollPolicy;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PayrollPolicyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('policy_key'),
            TextEntry::make('version'),
            TextEntry::make('name'),
            TextEntry::make('description')->placeholder('No description'),
            TextEntry::make('status')->badge(),
            TextEntry::make('effective_from')->date(),
            TextEntry::make('effective_until')->date()->placeholder('Open-ended'),
            TextEntry::make('rounding_mode')->placeholder('Not configured'),
            TextEntry::make('rounding_precision')->placeholder('Not configured'),
            TextEntry::make('parameters')->formatStateUsing(fn ($state): string => json_encode($state ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'),
            TextEntry::make('required_parameters')->formatStateUsing(fn ($state): string => implode(', ', $state ?? []))->placeholder('None configured'),
            TextEntry::make('calculation_order')->formatStateUsing(fn ($state): string => implode(' → ', $state ?? []))->placeholder('Not configured'),
            TextEntry::make('configuration_gaps')->label('Configuration gaps')
                ->state(fn (PayrollPolicy $record): string => implode(', ', $record->configurationGaps()) ?: 'None'),
            TextEntry::make('createdBy.name')->label('Created by')->placeholder('Unknown'),
            TextEntry::make('activatedBy.name')->label('Activated by')->placeholder('Not activated'),
            TextEntry::make('activated_at')->dateTime()->placeholder('Not activated'),
            TextEntry::make('retiredBy.name')->label('Retired by')->placeholder('Not retired'),
            TextEntry::make('retired_at')->dateTime()->placeholder('Not retired'),
        ]);
    }
}
