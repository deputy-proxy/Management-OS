<?php

namespace App\Filament\Resources\TestSubmissions\Pages;

use App\Filament\Resources\TestSubmissions\TestSubmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestSubmission extends CreateRecord
{
    protected static string $resource = TestSubmissionResource::class;
}

