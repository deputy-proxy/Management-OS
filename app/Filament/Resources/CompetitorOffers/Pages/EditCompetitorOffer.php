<?php

namespace App\Filament\Resources\CompetitorOffers\Pages;

use App\Filament\Resources\CompetitorOffers\CompetitorOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditCompetitorOffer extends EditRecord
{
    protected static string $resource = CompetitorOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

