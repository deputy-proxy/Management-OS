<?php

namespace App\Filament\Resources\PaidCampaigns\Pages;

use App\Filament\Resources\PaidCampaigns\PaidCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPaidCampaign extends ViewRecord
{
    protected static string $resource = PaidCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

