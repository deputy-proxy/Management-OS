<?php

namespace App\Filament\Resources\VendorContracts\Pages;

use App\Filament\Resources\VendorContracts\VendorContractResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditVendorContract extends EditRecord
{
    protected static string $resource = VendorContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

