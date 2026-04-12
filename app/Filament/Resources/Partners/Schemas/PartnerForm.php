<?php

namespace App\Filament\Resources\Partners\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PartnerForm
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
                Forms\Components\TextInput::make('contact_person_email')
                    ->required(),
                Forms\Components\TextInput::make('contact_person_phone')
                    ->required(),
                Forms\Components\KeyValue::make('collaborations')
                    ->required(),
            ]);
    }
}
