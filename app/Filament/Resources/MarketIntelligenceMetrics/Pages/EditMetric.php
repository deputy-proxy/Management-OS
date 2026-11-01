<?php

namespace App\Filament\Resources\MarketIntelligenceMetrics\Pages;

use App\Filament\Resources\MarketIntelligenceMetrics\MarketIntelligenceMetricResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMetric extends EditRecord
{
    protected static string $resource = MarketIntelligenceMetricResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
