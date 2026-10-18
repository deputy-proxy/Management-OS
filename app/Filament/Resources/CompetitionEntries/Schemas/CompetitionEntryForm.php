<?php

namespace App\Filament\Resources\CompetitionEntries\Schemas;

use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class CompetitionEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('competition_id')
                    ->relationship('competition', 'title')
                    ->required(),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\TextInput::make('first_name')
                    ->required(),
                Forms\Components\TextInput::make('last_name')
                    ->required(),
                Forms\Components\TextInput::make('full_name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->rules(fn (Get $get): array => [
                        Rule::unique('competition_entries', 'email')
                            ->where('competition_id', $get('competition_id'))
                            ->ignore(request()->route('record')),
                    ]),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending_review' => 'Pending review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending_review')
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->required(),
                Textarea::make('content')
                    ->required(),
                Forms\Components\TextInput::make('attachment')
                    ->nullable(),
            ]);
    }
}
