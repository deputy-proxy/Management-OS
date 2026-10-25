<?php

namespace App\Filament\Resources\PaymentAllocations\Pages;

use App\Filament\Resources\PaymentAllocations\PaymentAllocationResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentAllocations extends ListRecords
{
    protected static string $resource = PaymentAllocationResource::class;
}
