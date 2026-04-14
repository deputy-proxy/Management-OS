<?php

namespace App\Filament\Resources\CoursePrices\Pages;

use App\Filament\Resources\CoursePrices\CoursePriceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditCoursePrice extends EditRecord
{
    protected static string $resource = CoursePriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

