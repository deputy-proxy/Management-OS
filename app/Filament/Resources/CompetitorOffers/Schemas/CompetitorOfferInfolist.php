<?php

namespace App\Filament\Resources\CompetitorOffers\Schemas;

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
            ]);
    }
}
