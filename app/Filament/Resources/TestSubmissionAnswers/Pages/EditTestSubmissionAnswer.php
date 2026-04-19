<?php

namespace App\Filament\Resources\TestSubmissionAnswers\Pages;

use App\Filament\Resources\TestSubmissionAnswers\TestSubmissionAnswerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTestSubmissionAnswer extends EditRecord
{
    protected static string $resource = TestSubmissionAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

