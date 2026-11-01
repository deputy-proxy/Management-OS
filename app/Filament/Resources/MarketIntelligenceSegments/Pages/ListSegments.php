<?php

namespace App\Filament\Resources\MarketIntelligenceSegments\Pages;

use App\Filament\Resources\MarketIntelligenceSegments\MarketIntelligenceSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSegments extends ListRecords
{
    protected static string $resource = MarketIntelligenceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
