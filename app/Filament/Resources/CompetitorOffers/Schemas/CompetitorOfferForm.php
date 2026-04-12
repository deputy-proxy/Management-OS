<?php

namespace App\Filament\Resources\CompetitorOffers\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CompetitorOfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Select::make('competitor_id')
                    ->relationship('competitor', 'name')
                    ->required(),
                Forms\Components\TextInput::make('offer_type')
                    ->required(),
                Forms\Components\Textarea::make('offer_description')
                    ->required(),
                Forms\Components\TextInput::make('offer_price')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('our_similar_offer')
                    ->required(),
                Forms\Components\Textarea::make('their_differentiator')
                    ->required(),
                Forms\Components\DatePicker::make('last_check')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
            ]);
    }
}
