<?php

namespace App\Console\Commands;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
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
        $this->auditCanonicalFinancialFields();
        $this->auditCanonicalCurrencyFormats();
        $this->auditCanonicalClassifications();
        $this->auditApprovedMappingConsistency();
        $this->auditApprovedMappingConsistency();
        $this->auditTransactionAccountCurrency();
        $this->auditBalanceAccountCurrency();
        $this->auditInvoiceSemantics();
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
        $samples = [];
        $count = 0;

        foreach ($rows as $row) {
            $expected = BigDecimal::of((string) $row->manual_amount)->plus((string) $row->line_total)->toScale(2, RoundingMode::HALF_UP);
            $actual = BigDecimal::of((string) $row->amount)->toScale(2, RoundingMode::HALF_UP);

            if ($expected->compareTo($actual) !== 0) {
                $count++;
                if (count($samples) < 5) {
                    $samples[] = (int) $row->id;
                }
            }
        }

        if ($count > 0) {
            $this->findings[] = 'payslip total mismatch';
            $this->warn(sprintf('payslip total mismatch: count=%d; employee_pay_slips.id samples=%s; monetary values withheld; reconcile baseline and line items against approved source records', $count, implode(',', $samples)));
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
            $this->warn('paid payslip missing payment date: '.$count.'; verify payment evidence before recording a date');
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
                'missing payslip source record: source_type=%s; count=%d; employee_pay_slip_items.id samples=%s; verify source links before correction',
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

        $this->line('Invoice-linked transaction summaries (informational only; no amount totals are printed):');

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
                ->groupBy($foreignKey)
                ->orderBy($foreignKey)
                ->limit(100)
                ->get();

            foreach ($rows as $row) {
                $this->line(sprintf(
                    '%s id=%d; active transaction count=%d',
                    $label,
                    $row->{$foreignKey},
                    $row->transaction_count,
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

        $duplicateQuery = DB::table('employee_pay_slips')
            ->select('employee_id', 'month')
            ->selectRaw('COUNT(*) AS duplicate_count')
            ->groupBy('employee_id', 'month')
            ->havingRaw('COUNT(*) > 1');
        $count = DB::query()->fromSub(clone $duplicateQuery, 'duplicate_periods')->count();
        $duplicates = $duplicateQuery->limit(10)->get();
        $samples = [];

        foreach ($duplicates as $duplicate) {
            if (count($samples) < 5) {
                $ids = DB::table('employee_pay_slips')
                    ->where('employee_id', $duplicate->employee_id)
                    ->where('month', $duplicate->month)
                    ->limit(5 - count($samples))
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
                $samples = array_merge($samples, $ids);
            }
        }

        if ($count > 0) {
            $this->findings[] = 'duplicate payslip period';
            $this->warn(sprintf('duplicate payslip period: duplicate groups=%d; employee_pay_slips.id samples=%s; employee/date details withheld; reconcile source periods before correction', $count, implode(',', $samples)));
        }
    }

    private function auditCanonicalFinancialFields(): void
    {
        $fields = [
            'transactions' => ['amount' => 'amount_decimal'],
            'customer_invoices' => ['amount_without_vat' => 'amount_without_vat_decimal', 'vat' => 'vat_decimal'],
            'vendor_invoices' => ['amount' => 'amount_decimal', 'amount_without_vat' => 'amount_without_vat_decimal', 'vat' => 'vat_decimal'],
            'taxes' => ['amount' => 'amount_decimal'],
            'financial_account_balances' => ['current_balance' => 'current_balance_decimal'],
            'product_resources' => ['amount' => 'amount_decimal'],
            'product_costs' => ['resource_amount' => 'resource_amount_decimal', 'allocated_amount' => 'allocated_amount_decimal'],
            'product_revenues' => ['amount' => 'amount_decimal'],
            'product_prices' => ['price' => 'price'],
        ];

        foreach ($fields as $table => $amountFields) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($amountFields as $legacy => $canonical) {
                if (! Schema::hasColumn($table, $canonical)) {
                    continue;
                }
                if ($legacy !== $canonical && Schema::hasColumn($table, $legacy)) {
                    $this->reportQueryFinding(
                        'canonical amount unresolved: '.$table.'.'.$canonical,
                        DB::table($table)->whereNotNull($legacy)->whereNull($canonical),
                        $table,
                    );
                }
                $this->auditDecimalPrecision($table, $canonical);

                $currencyColumn = Schema::hasColumn($table, 'currency_code') ? 'currency_code' : (Schema::hasColumn($table, 'currency') ? 'currency' : null);
                if ($currencyColumn !== null) {
                    $this->reportQueryFinding(
                        'canonical amount without currency: '.$table.'.'.$canonical,
                        DB::table($table)->whereNotNull($canonical)->whereNull($currencyColumn),
                        $table,
                    );
                }
            }
        }

        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'amount_decimal')) {
            $this->reportQueryFinding(
                'negative canonical transaction amount',
                DB::table('transactions')->where('amount_decimal', '<', 0),
                'transactions',
            );
            if (Schema::hasColumn('transactions', 'amount')) {
                $this->reportQueryFinding(
                    'negative legacy transaction amount needs refund/reversal classification',
                    DB::table('transactions')->where('amount', '<', 0),
                    'transactions',
                );
            }
        }
    }

    private function auditDecimalPrecision(string $table, string $column): void
    {
        $count = 0;
        $samples = [];
        DB::table($table)->select(['id', $column])->whereNotNull($column)->orderBy('id')->chunkById(500, function ($rows) use ($column, &$count, &$samples): void {
            foreach ($rows as $row) {
                $value = trim((string) $row->{$column});
                $valid = preg_match('/^-?\d+(?:\.(\d+))?$/', $value, $matches) === 1;
                if ($valid && strlen($matches[1] ?? '') <= 2) {
                    continue;
                }
                $count++;
                if (count($samples) < 5) {
                    $samples[] = (int) $row->id;
                }
            }
        });

        if ($count > 0) {
            $this->reportSampledFinding('invalid canonical decimal precision: '.$table.'.'.$column, $table, $count, $samples);
        }
    }

    private function auditCanonicalCurrencyFormats(): void
    {
        $tables = ['transactions', 'customer_invoices', 'vendor_invoices', 'taxes', 'financial_account_balances', 'product_resources', 'product_costs', 'product_revenues'];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency_code')) {
                continue;
            }

            $count = 0;
            $samples = [];
            DB::table($table)->select(['id', 'currency_code'])->whereNotNull('currency_code')->orderBy('id')->chunkById(500, function ($rows) use (&$count, &$samples): void {
                foreach ($rows as $row) {
                    if (preg_match('/^[A-Z]{3}$/', (string) $row->currency_code) === 1) {
                        continue;
                    }
                    $count++;
                    if (count($samples) < 5) {
                        $samples[] = (int) $row->id;
                    }
                }
            });

            if ($count > 0) {
                $this->reportSampledFinding('invalid canonical currency-code format: '.$table.'.currency_code', $table, $count, $samples);
            }
        }

        foreach ([
            'financial_accounts' => 'currency',
            'financial_account_balances' => 'currency',
            'vendor_invoices' => 'currency',
            'product_prices' => 'currency',
        ] as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $count = 0;
            $samples = [];
            DB::table($table)->select(['id', $column])->whereNotNull($column)->orderBy('id')->chunkById(500, function ($rows) use ($column, &$count, &$samples): void {
                foreach ($rows as $row) {
                    if (preg_match('/^[A-Z]{3}$/', (string) $row->{$column}) === 1) {
                        continue;
                    }
                    $count++;
                    if (count($samples) < 5) {
                        $samples[] = (int) $row->id;
                    }
                }
            });
            if ($count > 0) {
                $this->reportSampledFinding('invalid stored currency-code format: '.$table.'.'.$column, $table, $count, $samples);
            }
        }
    }

    private function auditCanonicalClassifications(): void
    {
        foreach ([
            ['transactions', 'transaction_type', 'transaction_type_code'],
            ['customer_invoices', 'status', 'status_code'],
            ['vendor_invoices', 'status', 'status_code'],
            ['taxes', 'status', 'status_code'],
        ] as [$table, $legacy, $canonical]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $canonical)) {
                continue;
            }
            if (Schema::hasColumn($table, $legacy)) {
                $this->reportQueryFinding('legacy classification unresolved: '.$table.'.'.$canonical, DB::table($table)->whereNotNull($legacy)->whereNull($canonical), $table);
            }

            $count = 0;
            $samples = [];
            DB::table($table)->select(['id', $canonical])->whereNotNull($canonical)->orderBy('id')->chunkById(500, function ($rows) use ($canonical, &$count, &$samples): void {
                foreach ($rows as $row) {
                    if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) $row->{$canonical}) === 1) {
                        continue;
                    }
                    $count++;
                    if (count($samples) < 5) {
                        $samples[] = (int) $row->id;
                    }
                }
            });
            if ($count > 0) {
                $this->reportSampledFinding('invalid canonical classification format: '.$table.'.'.$canonical, $table, $count, $samples);
            }
        }
    }

    private function auditApprovedMappingConsistency(): void
    {
        foreach (config('financial-normalization.tables', []) as $table => $definition) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($definition['amounts'] ?? [] as $mapping) {
                $source = $mapping['source'] ?? null;
                $target = $mapping['target'] ?? null;
                $unit = $mapping['unit'] ?? null;
                if (! is_string($source) || ! is_string($target) || ! in_array($unit, ['major', 'minor'], true)
                    || ! Schema::hasColumn($table, $source) || ! Schema::hasColumn($table, $target)) {
                    continue;
                }

                $count = 0;
                $samples = [];
                DB::table($table)->select(['id', $source, $target])->whereNotNull($source)->whereNotNull($target)->orderBy('id')->chunkById(500, function ($rows) use ($source, $target, $unit, &$count, &$samples): void {
                    foreach ($rows as $row) {
                        $raw = trim((string) $row->{$source});
                        if (! preg_match('/^-?\d+$/', $raw)) {
                            $count++;
                        } else {
                            $expected = $this->formatMappedLegacyAmount($raw, $unit);
                            if (BigDecimal::of($expected)->compareTo(BigDecimal::of((string) $row->{$target})) === 0) {
                                continue;
                            }
                            $count++;
                        }
                        if (count($samples) < 5) {
                            $samples[] = (int) $row->id;
                        }
                    }
                });
                if ($count > 0) {
                    $this->reportSampledFinding('canonical amount disagrees with approved source mapping: '.$table.'.'.$target, $table, $count, $samples);
                }
            }

            foreach (['currencies', 'classifications'] as $group) {
                foreach ($definition[$group] ?? [] as $mapping) {
                    $source = $mapping['source'] ?? null;
                    $target = $mapping['target'] ?? null;
                    $map = $mapping['map'] ?? [];
                    if (! is_string($source) || ! is_string($target) || $map === []
                        || ! Schema::hasColumn($table, $source) || ! Schema::hasColumn($table, $target)) {
                        continue;
                    }

                    $count = 0;
                    $samples = [];
                    DB::table($table)->select(['id', $source, $target])->whereNotNull($source)->whereNotNull($target)->orderBy('id')->chunkById(500, function ($rows) use ($source, $target, $map, &$count, &$samples): void {
                        foreach ($rows as $row) {
                            $sourceValue = (string) $row->{$source};
                            if (! array_key_exists($sourceValue, $map) || (string) $map[$sourceValue] !== (string) $row->{$target}) {
                                $count++;
                                if (count($samples) < 5) {
                                    $samples[] = (int) $row->id;
                                }
                            }
                        }
                    });
                    if ($count > 0) {
                        $this->reportSampledFinding('canonical value disagrees with approved exact mapping: '.$table.'.'.$target, $table, $count, $samples);
                    }
                }
            }
        }
    }

    private function formatMappedLegacyAmount(string $value, string $unit): string
    {
        $negative = str_starts_with($value, '-');
        $digits = ltrim($negative ? substr($value, 1) : $value, '0');
        $digits = $digits === '' ? '0' : $digits;
        if ($unit === 'major') {
            $formatted = $digits.'.00';
        } else {
            $digits = str_pad($digits, 3, '0', STR_PAD_LEFT);
            $formatted = substr($digits, 0, -2).'.'.substr($digits, -2);
        }

        return $negative && trim(str_replace('.', '', $formatted), '0') !== '' ? '-'.$formatted : $formatted;
    }

    private function auditTransactionAccountCurrency(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('financial_accounts')
            || ! Schema::hasColumn('transactions', 'currency_code') || ! Schema::hasColumn('financial_accounts', 'currency')) {
            return;
        }

        $this->auditCurrencyPair('transactions', 'financial_account_id', 'currency_code', 'financial_accounts', 'currency', 'transaction/account currency mismatch');
    }

    private function auditBalanceAccountCurrency(): void
    {
        if (! Schema::hasTable('financial_account_balances') || ! Schema::hasTable('financial_accounts')
            || ! Schema::hasColumn('financial_account_balances', 'currency_code') || ! Schema::hasColumn('financial_accounts', 'currency')) {
            return;
        }

        $this->auditCurrencyPair('financial_account_balances', 'financial_account_id', 'currency_code', 'financial_accounts', 'currency', 'balance/account currency mismatch');
    }

    private function auditCurrencyPair(string $childTable, string $foreignKey, string $childCurrency, string $parentTable, string $parentCurrency, string $label): void
    {
        $count = 0;
        $samples = [];
        DB::table($childTable.' as child')
            ->join($parentTable.' as parent', 'parent.id', '=', 'child.'.$foreignKey)
            ->select(['child.id as id', 'child.'.$childCurrency.' as child_currency', 'parent.'.$parentCurrency.' as parent_currency'])
            ->whereNotNull('child.'.$childCurrency)
            ->whereNotNull('parent.'.$parentCurrency)
            ->orderBy('child.id')
            ->chunkById(500, function ($rows) use (&$count, &$samples): void {
                foreach ($rows as $row) {
                    $child = (string) $row->child_currency;
                    $parent = (string) $row->parent_currency;
                    if (preg_match('/^[A-Z]{3}$/', $child) !== 1 || preg_match('/^[A-Z]{3}$/', $parent) !== 1 || $child === $parent) {
                        continue;
                    }
                    $count++;
                    if (count($samples) < 5) {
                        $samples[] = (int) $row->id;
                    }
                }
            }, 'child.id', 'id');

        if ($count > 0) {
            $this->reportSampledFinding($label, $childTable, $count, $samples);
        }
    }

    private function auditInvoiceSemantics(): void
    {
        foreach (['customer_invoices', 'vendor_invoices'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $rule = config('financial-normalization.tables.'.$table.'.invoice_semantics.rule');
            if ($rule === null) {
                $this->reportQueryFinding('invoice semantics unresolved: '.$table, DB::table($table), $table);

                continue;
            }

            if ($table === 'customer_invoices' && $rule === 'customer_vat_zero') {
                $query = DB::table($table)->where('vat', '<>', 0);
                if (Schema::hasColumn($table, 'vat_decimal')) {
                    $query->orWhere('vat_decimal', '<>', 0);
                }
                $this->reportQueryFinding('customer invoice VAT violates configured rule', $query, $table);

                continue;
            }

            if ($table === 'vendor_invoices' && $rule === 'vendor_total_equals_components') {
                $count = 0;
                $samples = [];
                DB::table($table)->select(['id', 'amount', 'amount_without_vat', 'vat', 'amount_decimal', 'amount_without_vat_decimal', 'vat_decimal'])->orderBy('id')->chunkById(500, function ($rows) use (&$count, &$samples): void {
                    foreach ($rows as $row) {
                        $values = [$row->amount, $row->amount_without_vat, $row->vat];
                        $integerValues = collect($values)->every(fn ($value): bool => is_numeric($value) && preg_match('/^-?\d+$/', (string) $value) === 1);
                        $legacyMatches = $integerValues && (int) $row->amount === (int) $row->amount_without_vat + (int) $row->vat;
                        $canonicalPresent = $row->amount_decimal !== null && $row->amount_without_vat_decimal !== null && $row->vat_decimal !== null;
                        $canonicalMatches = ! $canonicalPresent || BigDecimal::of((string) $row->amount_decimal)->compareTo(BigDecimal::of((string) $row->amount_without_vat_decimal)->plus((string) $row->vat_decimal)) === 0;
                        if ($legacyMatches && $canonicalMatches) {
                            continue;
                        }
                        $count++;
                        if (count($samples) < 5) {
                            $samples[] = (int) $row->id;
                        }
                    }
                });
                if ($count > 0) {
                    $this->reportSampledFinding('vendor invoice total/component mismatch or malformed amount', $table, $count, $samples);
                }

                continue;
            }

            $this->reportQueryFinding('unsupported invoice semantic rule: '.$table, DB::table($table), $table);
        }
    }

    private function reportQueryFinding(string $label, Builder $query, string $table): void
    {
        $count = (clone $query)->count();
        if ($count === 0) {
            return;
        }
        $samples = (clone $query)->limit(5)->pluck($table.'.id')->map(fn ($id): int => (int) $id)->all();
        $this->reportSampledFinding($label, $table, $count, $samples);
    }

    /** @param array<int, int> $samples */
    private function reportSampledFinding(string $label, string $table, int $count, array $samples): void
    {
        if ($count === 0) {
            return;
        }
        $this->findings[] = $label;
        $this->warn(sprintf('%s: count=%d; %s.id samples=%s; sensitive values withheld', $label, $count, $table, implode(',', array_slice($samples, 0, 5))));
    }
}
