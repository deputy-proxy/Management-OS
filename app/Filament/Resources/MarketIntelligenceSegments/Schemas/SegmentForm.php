<?php

namespace App\Filament\Resources\MarketIntelligenceSegments\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class SegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('code')->required()->maxLength(60)->regex('/^[a-z0-9][a-z0-9._-]{0,59}$/')->helperText('Stable lowercase segment code.'),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Toggle::make('is_provisional')->default(true)->helperText('Keep enabled until a business owner confirms this definition.'),
        ]);
    }
}
