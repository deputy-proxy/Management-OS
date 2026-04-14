<?php

namespace App\Filament\Resources\JobDescriptions\Pages;

use App\Filament\Resources\JobDescriptions\JobDescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditJobDescription extends EditRecord
{
    protected static string $resource = JobDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

