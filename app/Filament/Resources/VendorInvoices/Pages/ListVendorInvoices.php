<?php

namespace App\Filament\Resources\VendorInvoices\Pages;

use App\Filament\Resources\VendorInvoices\VendorInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendorInvoices extends ListRecords
{
    protected static string $resource = VendorInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

