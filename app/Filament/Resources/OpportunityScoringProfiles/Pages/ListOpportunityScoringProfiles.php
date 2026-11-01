<?php

namespace App\Filament\Resources\OpportunityScoringProfiles\Pages;

use App\Filament\Resources\OpportunityScoringProfiles\OpportunityScoringProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOpportunityScoringProfiles extends ListRecords
{
    protected static string $resource = OpportunityScoringProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
