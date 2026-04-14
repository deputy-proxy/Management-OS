<?php

namespace App\Filament\Resources\CustomerInteractions\Pages;

use App\Filament\Resources\CustomerInteractions\CustomerInteractionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditCustomerInteraction extends EditRecord
{
    protected static string $resource = CustomerInteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

