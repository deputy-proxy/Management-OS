<?php

namespace App\Filament\Resources\JobDescriptions\Pages;

use App\Filament\Resources\JobDescriptions\JobDescriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobDescription extends CreateRecord
{
    protected static string $resource = JobDescriptionResource::class;
}

