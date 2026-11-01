<?php

namespace App\Filament\Resources\MarketIntelligenceSegments\Pages;

use App\Filament\Resources\MarketIntelligenceSegments\MarketIntelligenceSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSegment extends EditRecord
{
    protected static string $resource = MarketIntelligenceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
