<?php

namespace App\Filament\Resources\PaidCampaigns\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PaidCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('network')
                    ->required(),
                Forms\Components\DatePicker::make('scheduled_to_start_on')
                    ->required(),
                Forms\Components\DatePicker::make('started_on')
                    ->required(),
                Forms\Components\DatePicker::make('scheduled_to_end_on')
                    ->required(),
                Forms\Components\DatePicker::make('ended_on')
                    ->required(),
                Forms\Components\TextInput::make('budget')->numeric()
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
            ]);
    }
}
