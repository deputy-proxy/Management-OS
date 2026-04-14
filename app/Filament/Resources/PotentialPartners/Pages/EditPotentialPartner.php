<?php

namespace App\Filament\Resources\PotentialPartners\Pages;

use App\Filament\Resources\PotentialPartners\PotentialPartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditPotentialPartner extends EditRecord
{
    protected static string $resource = PotentialPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

