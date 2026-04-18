<?php

namespace App\Filament\Resources\TestTypes\Pages;

use App\Filament\Resources\TestTypes\TestTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestType extends CreateRecord
{
    protected static string $resource = TestTypeResource::class;
}

