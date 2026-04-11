<?php

namespace App\Filament\Resources\VendorCategories\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class VendorCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required(),
            ]);
    }
}
