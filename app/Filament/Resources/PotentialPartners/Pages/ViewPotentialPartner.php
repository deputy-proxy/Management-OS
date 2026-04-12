<?php

namespace App\Filament\Resources\PotentialPartners\Pages;

use App\Filament\Resources\PotentialPartners\PotentialPartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPotentialPartner extends ViewRecord
{
    protected static string $resource = PotentialPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

