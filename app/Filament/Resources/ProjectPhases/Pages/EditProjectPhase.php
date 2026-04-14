<?php

namespace App\Filament\Resources\ProjectPhases\Pages;

use App\Filament\Resources\ProjectPhases\ProjectPhaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditProjectPhase extends EditRecord
{
    protected static string $resource = ProjectPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

