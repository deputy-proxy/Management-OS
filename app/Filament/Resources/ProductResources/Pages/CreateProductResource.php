<?php

namespace App\Filament\Resources\ProductResources\Pages;

use App\Filament\Resources\ProductResources\ProductResourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductResource extends CreateRecord
{
    protected static string $resource = ProductResourceResource::class;
}

