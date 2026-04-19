<?php

namespace App\Filament\Resources\TestSubmissionAnswers\Pages;

use App\Filament\Resources\TestSubmissionAnswers\TestSubmissionAnswerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTestSubmissionAnswers extends ListRecords
{
    protected static string $resource = TestSubmissionAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

