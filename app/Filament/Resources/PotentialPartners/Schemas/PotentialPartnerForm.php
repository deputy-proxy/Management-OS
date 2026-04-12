<?php

namespace App\Filament\Resources\PotentialPartners\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PotentialPartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('site')
                    ->required(),
                Forms\Components\TextInput::make('contact_person_name')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\Textarea::make('benefits_for_us')
                    ->required(),
                Forms\Components\Textarea::make('benefits_for_them')
                    ->required(),
                Forms\Components\DatePicker::make('last_contact')
                    ->required(),
            ]);
    }
}
