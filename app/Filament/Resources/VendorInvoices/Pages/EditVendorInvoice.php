<?php

namespace App\Filament\Resources\VendorInvoices\Pages;

use App\Filament\Resources\VendorInvoices\VendorInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditVendorInvoice extends EditRecord
{
    protected static string $resource = VendorInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

