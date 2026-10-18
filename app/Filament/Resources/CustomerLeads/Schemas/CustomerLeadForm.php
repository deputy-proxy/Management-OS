<?php

namespace App\Filament\Resources\CustomerLeads\Schemas;

use App\Models\CustomerLead;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class CustomerLeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('The lead\'s full name and identity details.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('full_name')
                            ->disabled()
                            ->dehydrated(true)
                            ->placeholder('Will be filled automatically')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('full_name', self::buildFullName(
                                $get('first_name'),
                                $get('middle_name'),
                                $get('last_name')
                            ))
                            ),
                        Forms\Components\TextInput::make('middle_name'),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('full_name', self::buildFullName(
                                $get('first_name'),
                                $get('middle_name'),
                                $get('last_name')
                            ))
                            ),
                    ])
                    ->columns(3),

                Section::make('Contact Details')
                    ->description('How to reach the lead.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at')),
                        Forms\Components\TextInput::make('phone')
                            ->required()
                            ->tel()
                            ->maxLength(50),
                    ]),

                Section::make('Lead Interest')
                    ->description('What the lead is interested in and where they came from.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('source')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('topic_interest')
                            ->required(),
                        Forms\Components\TextInput::make('product_interest')
                            ->required(),
                    ]),

                Section::make('Contract Delivery')
                    ->description('Attach the existing contract PDF to make it available for sending to the lead.')
                    ->schema([
                        Forms\Components\FileUpload::make('contract_document')
                            ->label('Contract PDF')
                            ->disk('local')
                            ->directory('customer-lead-contracts')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->downloadable(),
                    ]),

                Section::make('Lead Status')
                    ->description('Current status and follow-up tracking for this lead.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(collect(CustomerLead::statuses())->except('converted')->all())
                            ->required()
                            ->rules([Rule::in(array_keys(collect(CustomerLead::statuses())->except('converted')->all()))])
                            ->disabled(fn (?CustomerLead $record): bool => $record?->status === 'converted'),
                        Forms\Components\DateTimePicker::make('last_contacted_at')
                            ->required(),
                    ]),
            ]);
    }

    private static function buildFullName(?string $first, ?string $middle, ?string $last): string
    {
        return collect([$first, $middle, $last])
            ->filter()
            ->implode(' ');
    }
}
