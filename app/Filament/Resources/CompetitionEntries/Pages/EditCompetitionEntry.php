<?php

namespace App\Filament\Resources\CompetitionEntries\Pages;

use App\Filament\Resources\CompetitionEntries\CompetitionEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditCompetitionEntry extends EditRecord
{
    protected static string $resource = CompetitionEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

