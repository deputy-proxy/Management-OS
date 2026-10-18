<?php

namespace App\Filament\Resources\ProductReviews\Schemas;

use Filament\Forms;
use Filament\Forms\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ProductReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('product_type')
                    ->options([
                        'course' => 'Course',
                        'camp' => 'Camp',
                        'workshop' => 'Workshop',
                        'event' => 'Event',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('product_id')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->rules(fn (Get $get): array => match ($get('product_type')) {
                        'course' => [Rule::exists('courses', 'id')],
                        'camp' => [Rule::exists('camps', 'id')],
                        'workshop' => [Rule::exists('workshops', 'id')],
                        'event' => [Rule::exists('events', 'id')],
                        default => [],
                    }),
                Forms\Components\TextInput::make('reviewer_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reviewer_email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->required()
                    ->maxLength(5000),
                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(5)
                    ->required(),
                Forms\Components\DateTimePicker::make('reviewed_on')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending_review' => 'Pending review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending_review')
                    ->required(),
            ]);
    }
}
