<?php

namespace App\Filament\Resources\ProductPrices\Schemas;

use App\Models\Camp;
use App\Models\Course;
use App\Models\Event;
use App\Models\Workshop;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('priceable_type')
                    ->label('Priced item type')
                    ->options([
                        'course' => 'Course',
                        'event' => 'Event',
                        'workshop' => 'Workshop',
                        'camp' => 'Camp',
                    ])
                    ->live()
                    ->required(),
                Forms\Components\Select::make('priceable_id')
                    ->label('Priced item')
                    ->options(fn (Get $get): array => match ($get('priceable_type')) {
                        'course' => Course::query()->orderBy('title')->pluck('title', 'id')->all(),
                        'event' => Event::query()->orderBy('title')->pluck('title', 'id')->all(),
                        'workshop' => Workshop::query()->orderBy('title')->pluck('title', 'id')->all(),
                        'camp' => Camp::query()->orderBy('title')->pluck('title', 'id')->all(),
                        default => [],
                    })
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->label('Currency code')
                    ->maxLength(3),
                Forms\Components\Select::make('price_type')
                    ->options(['standard' => 'Standard'])
                    ->default('standard')
                    ->required(),
                Forms\Components\DatePicker::make('valid_from'),
                Forms\Components\DatePicker::make('valid_until')
                    ->afterOrEqual('valid_from'),
            ]);
    }
}