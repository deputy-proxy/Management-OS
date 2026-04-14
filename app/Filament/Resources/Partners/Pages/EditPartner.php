<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditPartner extends EditRecord
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

