<?php

namespace App\Filament\Resources\CompetitorOffers\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CompetitorOfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('competitor_id'),
                TextEntry::make('offer_type'),
                TextEntry::make('offer_description'),
                TextEntry::make('offer_price'),
                TextEntry::make('our_similar_offer'),
                TextEntry::make('their_differentiator'),
                TextEntry::make('last_check')->dateTime(),
                TextEntry::make('status'),
                TextEntry::make('currency')->placeholder('Unknown'),
                TextEntry::make('price_basis')->placeholder('Unknown'),
                TextEntry::make('source_url')->url(fn (?string $state): ?string => $state)->placeholder('Unknown'),
                TextEntry::make('valid_from')->date()->placeholder('Unknown'),
                TextEntry::make('valid_until')->date()->placeholder('Unknown'),
                TextEntry::make('observed_at')->dateTime()->placeholder('Unknown'),
                RepeatableEntry::make('observations')
                    ->label('Historical offer observations')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('offer_type'),
                        TextEntry::make('offer_description'),
                        TextEntry::make('offer_price')->placeholder('Unknown price'),
                        TextEntry::make('currency')->placeholder('Unknown currency'),
                        TextEntry::make('price_basis')->placeholder('Unknown basis'),
                        TextEntry::make('price_comparison')
                            ->state(fn ($record): string => $record->priceComparisonLabel()),
                        TextEntry::make('source_url')
                            ->url(fn (?string $state): ?string => $state)
                            ->placeholder('Source unknown'),
                        TextEntry::make('source_status'),
                        TextEntry::make('observed_at')->dateTime(),
                        TextEntry::make('recorded_at')->dateTime(),
                        TextEntry::make('recorder.name')->placeholder('System / unknown recorder'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
