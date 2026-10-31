<?php

namespace App\Filament\Resources\ProductResources\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductResourceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('amount'),
                TextEntry::make('allocated_percentage'),
                TextEntry::make('month')
                    ->dateTime(),
                RepeatableEntry::make('allocationSnapshots')
                    ->label('Allocation history')
                    ->schema([
                        TextEntry::make('version'),
                        TextEntry::make('status'),
                        TextEntry::make('targetable_type'),
                        TextEntry::make('targetable_id'),
                        TextEntry::make('targetable.title')
                            ->label('Target'),
                        TextEntry::make('source_type'),
                        TextEntry::make('source_id'),
                        TextEntry::make('allocation_percent'),
                        TextEntry::make('allocated_amount_decimal'),
                        TextEntry::make('currency_code'),
                        TextEntry::make('source_snapshot')
                            ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT) ?: ''),
                        TextEntry::make('rule_snapshot')
                            ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT) ?: ''),
                    ]),
            ]);
    }
}
