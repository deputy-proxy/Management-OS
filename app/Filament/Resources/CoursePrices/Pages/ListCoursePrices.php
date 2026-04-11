<?php

namespace App\Filament\Resources\CoursePrices\Pages;

use App\Filament\Resources\CoursePrices\CoursePriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCoursePrices extends ListRecords
{
    protected static string $resource = CoursePriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

