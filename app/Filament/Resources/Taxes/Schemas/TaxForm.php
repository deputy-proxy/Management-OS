<?php
namespace App\Filament\Resources\Taxes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Termwind\Components\Span;

class TaxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tax Details')
                    ->description('The category, billing month and amount for this tax entry.')
                    ->columns(2)
                    ->schema([
                        Select::make('category')
                            ->options([
                                'vat' => 'VAT',
                                'labor' => 'Labor Tax',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('month')
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required(),
                    ]),

                Section::make('Payment Status')
                    ->description('Current status and key dates for this tax obligation.')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'overdue' => 'Overdue',
                            ])
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\DatePicker::make('due_on')
                            ->required(),
                        Forms\Components\DatePicker::make('paid_on')
                            ->required(),
                    ]),
            ]);
    }
}