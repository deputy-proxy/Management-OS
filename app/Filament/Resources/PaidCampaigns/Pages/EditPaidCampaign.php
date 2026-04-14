<?php

namespace App\Filament\Resources\PaidCampaigns\Pages;

use App\Filament\Resources\PaidCampaigns\PaidCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditPaidCampaign extends EditRecord
{
    protected static string $resource = PaidCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

