<?php

namespace App\Filament\Resources\ContentTemplates\Pages;

use App\Filament\Resources\ContentTemplates\ContentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentTemplates extends ListRecords
{
    protected static string $resource = ContentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

