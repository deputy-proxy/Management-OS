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
                Forms\Components\TextInput::make('currency')
                    ->label('Price currency (ISO code)')
                    ->maxLength(3)
                    ->helperText('Leave blank for legacy/unknown currency; never infer it.'),
                Forms\Components\Select::make('price_basis')
                    ->label('Price basis')
                    ->options(['one_time' => 'One-time', 'monthly' => 'Monthly', 'annual' => 'Annual', 'per_unit' => 'Per unit', 'from' => 'Starting at', 'unknown' => 'Unknown'])
                    ->placeholder('Unknown'),
                Forms\Components\TextInput::make('source_url')
                    ->label('Source URL')
                    ->url()
                    ->maxLength(2048),
                Forms\Components\DatePicker::make('valid_from')
                    ->label('Offer valid from'),
                Forms\Components\DatePicker::make('valid_until')
                    ->label('Offer valid until'),
                Forms\Components\DateTimePicker::make('observed_at')
                    ->label('Observed at')
                    ->helperText('When the offer was actually observed, not when the record was edited.'),
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
