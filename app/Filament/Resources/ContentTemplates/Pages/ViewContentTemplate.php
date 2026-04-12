<?php

namespace App\Filament\Resources\ContentTemplates\Pages;

use App\Filament\Resources\ContentTemplates\ContentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContentTemplate extends ViewRecord
{
    protected static string $resource = ContentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

