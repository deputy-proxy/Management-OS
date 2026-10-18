<?php

namespace App\Console\Commands;

use App\Services\EmployeePaySlipService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

class GenerateMonthlyEmployeePaySlips extends Command
{
    protected $signature = 'employee-payslips:generate {--month= : Calendar month in YYYY-MM format; defaults to the current month}';

    protected $description = 'Create idempotent monthly employee payslips and add approved holiday/reimbursement items.';

    public function handle(EmployeePaySlipService $service): int
    {
        $monthOption = $this->option('month') ?: now()->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $monthOption)) {
            $this->error('The --month option must use YYYY-MM format.');

            return self::INVALID;
        }

        try {
            $month = CarbonImmutable::createFromFormat('!Y-m', (string) $monthOption);
        } catch (InvalidArgumentException) {
            $this->error('The --month option is not a valid calendar month.');

            return self::INVALID;
        }

        if (! $month) {
            $this->error('The --month option is not a valid calendar month.');

            return self::INVALID;
        }

        $created = $service->generateForMonth($month);
        $this->info(sprintf('Created %d payslip(s) for %s. Existing payslips were left unchanged except for missing automatic items on Open slips.', $created, $month->format('Y-m')));

        return self::SUCCESS;
    }
}
