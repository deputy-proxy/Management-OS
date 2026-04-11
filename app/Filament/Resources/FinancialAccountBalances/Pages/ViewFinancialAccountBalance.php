<?php

namespace App\Filament\Resources\FinancialAccountBalances\Pages;

use App\Filament\Resources\FinancialAccountBalances\FinancialAccountBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFinancialAccountBalance extends ViewRecord
{
    protected static string $resource = FinancialAccountBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

