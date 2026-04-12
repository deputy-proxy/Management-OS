<?php

namespace App\Filament\Resources\CompetitorOffers\Pages;

use App\Filament\Resources\CompetitorOffers\CompetitorOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCompetitorOffer extends ViewRecord
{
    protected static string $resource = CompetitorOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

