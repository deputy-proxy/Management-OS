<?php

namespace App\Filament\Resources\SessionAssistances\Pages;

use App\Filament\Resources\SessionAssistances\SessionAssistanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditSessionAssistance extends EditRecord
{
    protected static string $resource = SessionAssistanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

