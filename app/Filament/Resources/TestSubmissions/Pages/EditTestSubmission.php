<?php

namespace App\Filament\Resources\TestSubmissions\Pages;

use App\Filament\Resources\TestSubmissions\TestSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditTestSubmission extends EditRecord
{
    protected static string $resource = TestSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

