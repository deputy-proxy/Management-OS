<?php

namespace App\Filament\Resources\TestQuestions\Pages;

use App\Filament\Resources\TestQuestions\TestQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTestQuestions extends ListRecords
{
    protected static string $resource = TestQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

