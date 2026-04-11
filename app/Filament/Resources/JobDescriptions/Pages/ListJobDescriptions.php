<?php

namespace App\Filament\Resources\JobDescriptions\Pages;

use App\Filament\Resources\JobDescriptions\JobDescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobDescriptions extends ListRecords
{
    protected static string $resource = JobDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

