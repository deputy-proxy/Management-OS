<?php

namespace App\Filament\Resources\PayrollPolicies\Pages;

use App\Filament\Resources\PayrollPolicies\PayrollPolicyResource;
use Filament\Resources\Pages\EditRecord;

class EditPayrollPolicy extends EditRecord
{
    protected static string $resource = PayrollPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['status'], $data['activated_at'], $data['activated_by'], $data['created_by'], $data['updated_by'], $data['retired_at'], $data['retired_by']);

        return $data;
    }
}
