<?php
namespace App\Filament\Resources\CustomerInteractions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class CustomerInteractionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Interaction Context')
                    ->description('Who was involved in this interaction and what prompted it.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->required(),
                        Select::make('initiator')
                            ->options([
                                'Customer' => 'Customer',
                                'Coordinator' => 'Coordinator',
                            ])
                            ->required(),
                        Select::make('reason')
                            ->options([
                                'Product Inquiry' => 'Product Inquiry',
                                'Billing Question' => 'Billing Question',
                                'Complaint' => 'Complaint',
                                'Feedback' => 'Feedback',
                            ])
                            ->required(),
                    ]),

                Section::make('Interaction Details')
                    ->description('Full description and timing of the interaction.')
                    ->columns(1)
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->required(),
                        Forms\Components\DateTimePicker::make('timestamp')
                            ->required(),
                        Select::make('interaction_rating')
                            ->options([
                                1 => '1 - Poor',
                                2 => '2 - Fair',
                                3 => '3 - Good',
                                4 => '4 - Very Good',
                                5 => '5 - Excellent',
                            ])
                            ->required(),
                    ])
            ]);
    }
}