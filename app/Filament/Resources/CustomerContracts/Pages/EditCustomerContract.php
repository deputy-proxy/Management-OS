<?php

namespace App\Filament\Resources\CustomerContracts\Pages;

use App\Filament\Resources\CustomerContracts\CustomerContractResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditCustomerContract extends EditRecord
{
    protected static string $resource = CustomerContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

