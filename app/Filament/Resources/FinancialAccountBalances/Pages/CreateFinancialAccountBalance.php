<?php

namespace App\Filament\Resources\FinancialAccountBalances\Pages;

use App\Filament\Resources\FinancialAccountBalances\FinancialAccountBalanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancialAccountBalance extends CreateRecord
{
    protected static string $resource = FinancialAccountBalanceResource::class;
}

