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
        $this->auditPayslipSourceLinks();
        $this->auditTransactionInvoiceLinks();
        $this->reportInvoiceTransactionSummaries();

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

    private function auditPayslipSourceLinks(): void
    {
        if (! Schema::hasTable('employee_pay_slip_items')
            || ! Schema::hasColumn('employee_pay_slip_items', 'source_type')
            || ! Schema::hasColumn('employee_pay_slip_items', 'source_id')) {
            $this->warn('Payslip source-link check skipped: required schema is not present.');

            return;
        }

        $sources = [
            'session' => 'scheduled_sessions',
            'session_assistance' => 'session_assistances',
            'test_simulation' => 'test_simulations',
            'employee_contract' => 'employee_contracts',
            'employee_holiday' => 'employee_holidays',
            'employee_pay_slip_item' => 'employee_pay_slip_items',
        ];

        foreach ($sources as $sourceType => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table('employee_pay_slip_items as items')
                ->leftJoin($table.' as source', 'source.id', '=', 'items.source_id')
                ->where('items.source_type', $sourceType)
                ->whereNull('source.id');
            $count = $query->count();
            if ($count === 0) {
                continue;
            }

            $this->findings[] = 'missing payslip source record';
            $samples = (clone $query)->limit(5)->pluck('items.id')->map(fn ($id): int => (int) $id)->all();
            $this->warn(sprintf(
                'missing payslip source record: source_type=%s; count=%d; employee_pay_slip_items.id samples=%s',
                $sourceType,
                $count,
                implode(',', $samples),
            ));
        }

        $known = array_keys($sources);
        $unknown = DB::table('employee_pay_slip_items')
            ->whereNotIn('source_type', $known)
            ->select('source_type')
            ->selectRaw('COUNT(*) AS row_count')
            ->groupBy('source_type')
            ->limit(10)
            ->get();

        foreach ($unknown as $row) {
            $this->findings[] = 'unknown payslip source type';
            $this->warn(sprintf('unknown payslip source type: source_type=%s; count=%d', $row->source_type, $row->row_count));
        }
    }

    private function auditTransactionInvoiceLinks(): void
    {
        if (! Schema::hasTable('transactions')
            || ! Schema::hasColumn('transactions', 'customer_invoice_id')
            || ! Schema::hasColumn('transactions', 'vendor_invoice_id')) {
            return;
        }

        $query = DB::table('transactions')
            ->whereNotNull('customer_invoice_id')
            ->whereNotNull('vendor_invoice_id');
        $count = $query->count();

        if ($count > 0) {
            $this->findings[] = 'transaction linked to both customer and vendor invoices';
            $samples = (clone $query)->limit(5)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $this->warn(sprintf(
                'transaction linked to both customer and vendor invoices: count=%d; transactions.id samples=%s; verify intended semantics before correction',
                $count,
                implode(',', $samples),
            ));
        }
    }

    private function reportInvoiceTransactionSummaries(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'amount')) {
            return;
        }

        $this->line('Invoice-linked transaction summaries (informational only; not a balance assertion because payment allocation and amount semantics are not approved):');

        foreach ([
            ['customer_invoice_id', 'customer_invoices', 'customer invoice'],
            ['vendor_invoice_id', 'vendor_invoices', 'vendor invoice'],
        ] as [$foreignKey, $invoiceTable, $label]) {
            if (! Schema::hasColumn('transactions', $foreignKey) || ! Schema::hasTable($invoiceTable)) {
                continue;
            }

            $rows = DB::table('transactions')
                ->whereNull('deleted_at')
                ->whereNotNull($foreignKey)
                ->select($foreignKey)
                ->selectRaw('COUNT(*) AS transaction_count')
                ->selectRaw('SUM(amount) AS transaction_amount_total')
                ->groupBy($foreignKey)
                ->orderBy($foreignKey)
                ->limit(100)
                ->get();

            foreach ($rows as $row) {
                $this->line(sprintf(
                    '%s id=%d; active transactions=%d; raw amount sum=%s',
                    $label,
                    $row->{$foreignKey},
                    $row->transaction_count,
                    (string) ($row->transaction_amount_total ?? '0'),
                ));
            }
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
