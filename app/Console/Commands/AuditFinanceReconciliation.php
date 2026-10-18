<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditFinanceReconciliation extends Command
{
    protected $signature = 'finance:reconciliation-audit';

    protected $description = 'Read-only checks for configured finance and payroll reconciliation invariants.';

    /** @var array<int, string> */
    private array $findings = [];

    public function handle(): int
    {
        $this->info('Read-only finance reconciliation audit. No records will be changed.');

        $this->auditPayslipTotals();
        $this->auditPaidPayslipDates();
        $this->auditDuplicatePayslipPeriods();

        if ($this->findings === []) {
            $this->info('No configured finance reconciliation findings detected.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(count($this->findings).' finance reconciliation finding(s) detected. No data was modified. Reconcile against source evidence before making corrections.');

        return self::FAILURE;
    }

    private function auditPayslipTotals(): void
    {
        if (! Schema::hasTable('employee_pay_slips')
            || ! Schema::hasColumn('employee_pay_slips', 'manual_amount')
            || ! Schema::hasColumn('employee_pay_slips', 'amount')
            || ! Schema::hasTable('employee_pay_slip_items')) {
            $this->warn('Payslip total check skipped: required schema is not present.');

            return;
        }

        $rows = DB::table('employee_pay_slips as slips')
            ->leftJoin('employee_pay_slip_items as items', 'items.employee_pay_slip_id', '=', 'slips.id')
            ->select('slips.id', 'slips.manual_amount', 'slips.amount')
            ->selectRaw('COALESCE(SUM(items.amount), 0) AS line_total')
            ->groupBy('slips.id', 'slips.manual_amount', 'slips.amount')
            ->get();

        foreach ($rows as $row) {
            $expected = round((float) $row->manual_amount + (float) $row->line_total, 2, PHP_ROUND_HALF_UP);
            $actual = round((float) $row->amount, 2, PHP_ROUND_HALF_UP);

            if (abs($expected - $actual) > 0.00001) {
                $this->findings[] = 'payslip total mismatch';
                $this->warn(sprintf(
                    'payslip total mismatch: employee_pay_slips.id=%d; stored=%0.2f; baseline_plus_lines=%0.2f',
                    $row->id,
                    $actual,
                    $expected,
                ));
            }
        }
    }

    private function auditPaidPayslipDates(): void
    {
        if (! Schema::hasTable('employee_pay_slips')
            || ! Schema::hasColumn('employee_pay_slips', 'status')
            || ! Schema::hasColumn('employee_pay_slips', 'paid_on')) {
            return;
        }

        $count = DB::table('employee_pay_slips')
            ->whereRaw('LOWER(status) = ?', ['paid'])
            ->whereNull('paid_on')
            ->count();

        if ($count > 0) {
            $this->findings[] = 'paid payslip missing payment date';
            $this->warn('paid payslip missing payment date: '.$count);
        }
    }

    private function auditDuplicatePayslipPeriods(): void
    {
        if (! Schema::hasTable('employee_pay_slips')
            || ! Schema::hasColumn('employee_pay_slips', 'employee_id')
            || ! Schema::hasColumn('employee_pay_slips', 'month')) {
            return;
        }

        $duplicates = DB::table('employee_pay_slips')
            ->select('employee_id', 'month')
            ->selectRaw('COUNT(*) AS duplicate_count')
            ->groupBy('employee_id', 'month')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        foreach ($duplicates as $duplicate) {
            $this->findings[] = 'duplicate payslip period';
            $this->warn(sprintf(
                'duplicate payslip period: employee_id=%d; month=%s; count=%d',
                $duplicate->employee_id,
                $duplicate->month,
                $duplicate->duplicate_count,
            ));
        }
    }
}