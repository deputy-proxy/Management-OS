<?php

namespace App\Filament\Resources\ContentTemplates\Pages;

use App\Filament\Resources\ContentTemplates\ContentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditContentTemplate extends EditRecord
{
    protected static string $resource = ContentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

