<?php

namespace App\Filament\Resources\Competitors\Pages;

use App\Filament\Resources\Competitors\CompetitorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompetitors extends ListRecords
{
    protected static string $resource = CompetitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

