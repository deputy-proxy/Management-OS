<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Textarea::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description'),
                Forms\Components\Textarea::make('support'),
            ]);
    }
}
