<?php

namespace App\Filament\Resources\CourseMaterials\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class CourseMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
                Forms\Components\TextInput::make('attachment')
                    ->required(),
                Forms\Components\Select::make('added_by')
                    ->relationship('addedBy', 'name')
                    ->required()
            ]);
    }
}
