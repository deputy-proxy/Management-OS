<?php

namespace App\Filament\Resources\WorkshopPrices\Pages;

use App\Filament\Resources\WorkshopPrices\WorkshopPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditWorkshopPrice extends EditRecord
{
    protected static string $resource = WorkshopPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

