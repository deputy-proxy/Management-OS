<?php

namespace App\Filament\Resources\CoursePrices\Pages;

use App\Filament\Resources\CoursePrices\CoursePriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCoursePrice extends ViewRecord
{
    protected static string $resource = CoursePriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

