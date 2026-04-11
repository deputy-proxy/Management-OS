<?php

namespace App\Filament\Resources\FinancialAccountBalances\Pages;

use App\Filament\Resources\FinancialAccountBalances\FinancialAccountBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancialAccountBalances extends ListRecords
{
    protected static string $resource = FinancialAccountBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

