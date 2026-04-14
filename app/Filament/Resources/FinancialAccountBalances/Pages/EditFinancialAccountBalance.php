<?php

namespace App\Filament\Resources\FinancialAccountBalances\Pages;

use App\Filament\Resources\FinancialAccountBalances\FinancialAccountBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditFinancialAccountBalance extends EditRecord
{
    protected static string $resource = FinancialAccountBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),            CommentsAction::make(),

        ];
    }
}

