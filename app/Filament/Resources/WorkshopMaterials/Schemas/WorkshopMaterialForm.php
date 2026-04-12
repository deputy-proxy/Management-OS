<?php

namespace App\Filament\Resources\WorkshopMaterials\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class WorkshopMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Select::make('workshop_id')
                    ->relationship('workshop', 'title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\KeyValue::make('content')
                    ->required(),
                Forms\Components\Select::make('added_by')
                    ->relationship('addedBy', 'name')
                    ->required(),
            ]);
    }
}
