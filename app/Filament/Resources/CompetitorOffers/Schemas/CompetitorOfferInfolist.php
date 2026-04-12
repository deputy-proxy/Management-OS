<?php

namespace App\Filament\Resources\CompetitorOffers\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CompetitorOfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('competitor_id'),
                \Filament\Infolists\Components\TextEntry::make('offer_type'),
                \Filament\Infolists\Components\TextEntry::make('offer_description'),
                \Filament\Infolists\Components\TextEntry::make('offer_price'),
                \Filament\Infolists\Components\TextEntry::make('our_similar_offer'),
                \Filament\Infolists\Components\TextEntry::make('their_differentiator'),
                \Filament\Infolists\Components\TextEntry::make('last_check')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('status'),
            ]);
    }
}
