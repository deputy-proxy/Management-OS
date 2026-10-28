<?php

namespace App\Filament\Resources\PayrollPolicies\Pages;

use App\Filament\Resources\PayrollPolicies\PayrollPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollPolicies extends ListRecords
{
    protected static string $resource = PayrollPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => hexa()->can('payroll_policy.create'))];
    }
}
