<?php

namespace App\Filament\Resources\EmployeePaySlips\Pages;

use App\Filament\Resources\EmployeePaySlips\EmployeePaySlipResource;
use App\Models\EmployeeContract;
use App\Models\EmployeePaySlip;
use App\Services\EmployeePaySlipService;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateEmployeePaySlip extends CreateRecord
{
    protected static string $resource = EmployeePaySlipResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $month = CarbonImmutable::parse($data['month'])->startOfMonth();
        $duplicate = EmployeePaySlip::withTrashed()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('month', $month->toDateString())
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['month' => 'A payslip already exists for this employee and month.']);
        }

        $eligible = EmployeeContract::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('start_date', '<=', $month->endOfMonth()->toDateString())
            ->whereDate('end_date', '>=', $month->toDateString())
            ->exists();

        if (! $eligible) {
            throw ValidationException::withMessages(['employee_id' => 'The employee must have a contract overlapping the selected pay month.']);
        }

        $data['month'] = $month->toDateString();
        $data['status'] = 'Open';
        $data['paid_on'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        app(EmployeePaySlipService::class)->populateAutomaticItems($this->record);
    }
}
