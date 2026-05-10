<?php
namespace App\Filament\Resources\FinancialAccounts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class FinancialAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->description('Core details identifying this financial account.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required(),
                        Select::make('type')
                            ->required()
                            ->options([
                                'Current' => 'Current',
                                'Credit' => 'Credit',
                                'Cash' => 'Cash',
                            ]),
                        Select::make('currency')
                            ->required()
                            ->options([
                                'EUR' => 'Euro',
                                'GBP' => 'RON',
                            ]),
                        Select::make('bank')
                            ->required()
                            ->options([
                                'BCR' => 'Banca Comerciala Romana',
                                'ING' => 'ING Bank',
                                'Cash' => 'Cash',
                            ]),
                    ]),

                Section::make('Account Status')
                    ->description('Current standing and operational lifecycle of this account.')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->required()
                            ->options([
                                'Active' => 'Active',
                                'Inactive' => 'Inactive',
                                'Closed' => 'Closed',
                            ]),
                        Forms\Components\DatePicker::make('opened_on'),
                        Forms\Components\DatePicker::make('closed_on'),
                    ]),
            ]);
    }
}