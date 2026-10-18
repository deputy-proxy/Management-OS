<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditDatabaseIntegrity extends Command
{
    protected $signature = 'database:integrity-audit';

    protected $description = 'Read-only diagnostics for critical business-record integrity invariants.';

    /** @var array<int, string> */
    private array $findings = [];

    public function handle(): int
    {
        $this->info('Read-only integrity audit. No records will be changed.');

        $this->duplicates('products', ['productable_type', 'productable_id'], 'canonical product per productable', 'productable_type IS NOT NULL AND productable_id IS NOT NULL');
        $this->duplicates('product_reviews', ['product_type', 'product_id', 'customer_id'], 'active review per customer/product', 'deleted_at IS NULL');
        $this->duplicates('employee_pay_slips', ['employee_id', 'month'], 'payslip per employee/month', '1 = 1');
        $this->duplicates('employee_pay_slip_items', ['employee_pay_slip_id', 'source_type', 'source_id'], 'payslip source per payslip', '1 = 1');
        $this->duplicates('competition_entries', ['competition_id', 'email'], 'competition entry email per competition', '1 = 1');
        $this->duplicates('session_attendances', ['session_id', 'student_id'], 'active attendance per session/student', 'deleted_at IS NULL');
        $this->duplicates('customer_contracts', ['source_customer_lead_id'], 'contract per source lead', 'source_customer_lead_id IS NOT NULL');

        $this->orphans('session_attendances', 'session_id', 'scheduled_sessions', 'attendance session');
        $this->orphans('session_attendances', 'student_id', 'students', 'attendance student');
        $this->orphans('product_reviews', 'customer_id', 'customers', 'review customer');
        $this->orphans('customer_leads', 'converted_customer_id', 'customers', 'converted lead customer');
        $this->orphans('customer_contracts', 'source_customer_lead_id', 'customer_leads', 'contract source lead');
        $this->orphans('customer_invoices', 'customer_id', 'customers', 'customer invoice customer');
        $this->orphans('customer_invoices', 'customer_contract_id', 'customer_contracts', 'customer invoice contract');
        $this->orphans('vendor_invoices', 'vendor_id', 'vendors', 'vendor invoice vendor');
        $this->orphans('vendor_invoices', 'vendor_contract_id', 'vendor_contracts', 'vendor invoice contract');
        $this->orphans('product_revenues', 'product_id', 'products', 'product revenue product');
        $this->orphans('product_revenues', 'customer_invoice_id', 'customer_invoices', 'product revenue invoice');
        $this->orphans('employee_pay_slips', 'employee_id', 'employees', 'payslip employee');
        $this->orphans('employee_pay_slip_items', 'employee_pay_slip_id', 'employee_pay_slips', 'payslip source item parent');
        $this->orphans('transactions', 'financial_account_id', 'financial_accounts', 'transaction financial account');
        $this->orphans('transactions', 'customer_invoice_id', 'customer_invoices', 'transaction customer invoice');
        $this->orphans('transactions', 'vendor_invoice_id', 'vendor_invoices', 'transaction vendor invoice');
        $this->orphans('transactions', 'tax_id', 'taxes', 'transaction tax');

        if (Schema::hasTable('customer_leads')) {
            $invalidConverted = DB::table('customer_leads')->where('status', 'converted')
                ->where(function ($query): void {
                    $query->whereNull('converted_customer_id')->orWhereNull('converted_at');
                })->count();
            $this->reportCount('customer lead converted without customer/timestamp', $invalidConverted);
        }

        if ($this->findings === []) {
            $this->info('No configured integrity findings detected.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(count($this->findings).' integrity finding(s) detected. No data was modified. Review and reconcile explicitly before applying constraints.');

        return self::FAILURE;
    }

    /** @param array<int, string> $columns */
    private function duplicates(string $table, array $columns, string $label, string $where): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table)->select($columns)->selectRaw('COUNT(*) AS duplicate_count')->groupBy($columns)->havingRaw('COUNT(*) > 1')->limit(5);
        if ($where !== '1 = 1') {
            $query->whereRaw($where);
        }

        foreach ($query->get() as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $column.'='.($row->{$column} ?? 'NULL');
            }
            $this->findings[] = $label;
            $this->warn(sprintf('%s: %s; count=%d', $label, implode(', ', $values), $row->duplicate_count));
        }
    }

    private function orphans(string $table, string $foreignKey, string $parentTable, string $label): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        $query = DB::table($table.' as child')->leftJoin($parentTable.' as parent', 'parent.id', '=', 'child.'.$foreignKey)
            ->whereNotNull('child.'.$foreignKey)->whereNull('parent.id');
        $count = $query->count();
        $this->reportCount($label.' orphan reference', $count);
        if ($count > 0) {
            $sample = (clone $query)->value('child.id');
            $this->warn("  sample {$table}.id={$sample}");
        }
    }

    private function reportCount(string $label, int $count): void
    {
        if ($count === 0) {
            return;
        }

        $this->findings[] = $label;
        $this->warn($label.': '.$count);
    }
}
