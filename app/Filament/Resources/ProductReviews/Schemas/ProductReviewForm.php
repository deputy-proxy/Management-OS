<?php

namespace App\Filament\Resources\ProductReviews\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class ProductReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('product_type')
                    ->required(),
                Forms\Components\TextInput::make('product_id')
                    ->required(),
                Forms\Components\TextInput::make('reviewer_name')
                    ->required(),
                Forms\Components\TextInput::make('reviewer_email')
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->required(),
                Forms\Components\TextInput::make('rating')->numeric()
                    ->required(),
                Forms\Components\DateTimePicker::make('reviewed_on')
                    ->required(),
            ]);
    }
}
