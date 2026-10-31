<?php

namespace App\Filament\Resources\PayrollPolicies\Pages;

use App\Filament\Resources\PayrollPolicies\PayrollPolicyResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollPolicy extends CreateRecord
{
    protected static string $resource = PayrollPolicyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['activated_at'] = null;
        $data['activated_by'] = null;

        return $data;
    }
}
