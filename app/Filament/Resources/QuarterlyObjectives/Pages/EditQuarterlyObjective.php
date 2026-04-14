<?php

namespace App\Filament\Resources\QuarterlyObjectives\Pages;

use App\Filament\Resources\QuarterlyObjectives\QuarterlyObjectiveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditQuarterlyObjective extends EditRecord
{
    protected static string $resource = QuarterlyObjectiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

