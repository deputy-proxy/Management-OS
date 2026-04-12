<?php

namespace App\Filament\Resources\TestSubmissions\Pages;

use App\Filament\Resources\TestSubmissions\TestSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTestSubmissions extends ListRecords
{
    protected static string $resource = TestSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

