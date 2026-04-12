<?php

namespace App\Filament\Resources\PotentialPartners\Pages;

use App\Filament\Resources\PotentialPartners\PotentialPartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPotentialPartner extends EditRecord
{
    protected static string $resource = PotentialPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

