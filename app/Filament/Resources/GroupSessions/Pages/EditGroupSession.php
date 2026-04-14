<?php

namespace App\Filament\Resources\GroupSessions\Pages;

use App\Filament\Resources\GroupSessions\GroupSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditGroupSession extends EditRecord
{
    protected static string $resource = GroupSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

