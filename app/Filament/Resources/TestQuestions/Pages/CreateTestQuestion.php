<?php

namespace App\Filament\Resources\TestQuestions\Pages;

use App\Filament\Resources\TestQuestions\TestQuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestQuestion extends CreateRecord
{
    protected static string $resource = TestQuestionResource::class;
}

