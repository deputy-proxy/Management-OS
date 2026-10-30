<?php

namespace App\Services\Finance;

use App\Models\PaymentAllocation;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReceivablesPayablesData
{
    private const TIMEZONE = 'Europe/Bucharest';

    /**
     * @return array{
     *   as_of: string, timezone: string, generated_at: string,
     *   receivables: array<int, array<string, mixed>>,
     *   payables: array<int, array<string, mixed>>,
     *   receivable_totals: array<string, array<string, string|int>>,
     *   summary: array<string, int|bool>
     * }
     */
    public function report(CarbonImmutable $asOf): array
    {
        $asOf = $asOf->setTimezone(self::TIMEZONE)->startOfDay();
        $issuedInvoices = DB::table('issued_invoices')
            ->leftJoin('issued_invoice_parties as buyer', function ($join): void {
                $join->on('buyer.issued_invoice_id', '=', 'issued_invoices.id')
                    ->where('buyer.role', '=', 'buyer');
            })
            ->whereIn('issued_invoices.document_status', ['issued', 'corrected'])
            ->select([
                'issued_invoices.id', 'issued_invoices.legacy_customer_invoice_id',
                'issued_invoices.document_type', 'issued_invoices.document_status',
                'issued_invoices.invoice_number', 'issued_invoices.public_id',
                'issued_invoices.issue_date', 'issued_invoices.due_date',
                'issued_invoices.currency_code', 'issued_invoices.gross_total',
                'issued_invoices.issued_snapshot_hash', 'issued_invoices.corrects_issued_invoice_id',
                'buyer.legal_name as buyer_name',
            ])
            ->orderBy('issued_invoices.issue_date')
            ->orderBy('issued_invoices.id')
            ->get();

        $legacyIds = $issuedInvoices->pluck('legacy_customer_invoice_id')->filter()->unique()->values()->all();
        $legacyInvoices = $legacyIds === []
            ? collect()
            : DB::table('customer_invoices')->whereIn('id', $legacyIds)->get(['id', 'currency_code', 'deleted_at'])->keyBy('id');

        $customerAllocations = $this->loadAllocations('customer_invoice_id', $legacyIds);
        $customerTransactionIds = $customerAllocations->pluck('transaction_id')->unique()->values()->all();
        $customerPaymentTotals = $this->loadSourcePaymentTotals($customerTransactionIds);

        $receivables = [];
        $totals = [];
        $supportedCount = 0;
        $unresolvedReceivableCount = 0;

        foreach ($issuedInvoices as $invoice) {
            $row = $this->issuedInvoiceRow($invoice, $asOf, $legacyInvoices, $customerAllocations, $customerPaymentTotals);
            $receivables[] = $row;

            if ($row['balance_status'] === 'needs_review') {
                $unresolvedReceivableCount++;

                continue;
            }

            $supportedCount++;
            $currency = $row['currency_code'];
            $totals[$currency] ??= [
                'invoice_total' => BigDecimal::zero(),
                'confirmed_allocated' => BigDecimal::zero(),
                'outstanding' => BigDecimal::zero(),
                'credit' => BigDecimal::zero(),
                'invoice_count' => 0,
            ];
            $totals[$currency]['invoice_total'] = $totals[$currency]['invoice_total']->plus(BigDecimal::of($row['invoice_total']));
            $totals[$currency]['confirmed_allocated'] = $totals[$currency]['confirmed_allocated']->plus(BigDecimal::of($row['confirmed_allocated']));
            $totals[$currency]['outstanding'] = $totals[$currency]['outstanding']->plus(BigDecimal::of($row['outstanding_amount']));
            $totals[$currency]['credit'] = $totals[$currency]['credit']->plus(BigDecimal::of($row['credit_amount']));
            $totals[$currency]['invoice_count']++;
        }

        $mappedLegacyIds = $legacyIds;
        $legacyQuery = DB::table('customer_invoices')
            ->leftJoin('customers', 'customers.id', '=', 'customer_invoices.customer_id')
            ->whereNull('customer_invoices.deleted_at')
            ->when($mappedLegacyIds !== [], fn ($query) => $query->whereNotIn('customer_invoices.id', $mappedLegacyIds))
            ->select([
                'customer_invoices.id', 'customer_invoices.identifier', 'customer_invoices.month',
                'customer_invoices.status', 'customer_invoices.currency_code', 'customers.name as customer_name',
            ])
            ->orderBy('customer_invoices.month')
            ->orderBy('customer_invoices.id');

        foreach ($legacyQuery->get() as $invoice) {
            $receivables[] = [
                'source_type' => 'legacy_customer_invoice',
                'source_id' => (int) $invoice->id,
                'source_reference' => $invoice->identifier ?: 'Customer invoice #'.$invoice->id,
                'legacy_customer_invoice_id' => (int) $invoice->id,
                'entity' => $invoice->customer_name ?: 'Customer #'.$invoice->id,
                'document_date' => $invoice->month,
                'due_date' => null,
                'currency_code' => 'Unknown',
                'invoice_total' => null,
                'confirmed_allocated' => null,
                'outstanding_amount' => null,
                'credit_amount' => null,
                'allocation_count' => 0,
                'pending_allocation_count' => 0,
                'reversed_allocation_count' => 0,
                'allocation_history' => [],
                'balance_status' => 'needs_review',
                'payment_state' => 'unresolved_legacy_payment_state',
                'aging_bucket' => 'unknown',
                'days_overdue' => null,
                'reason_codes' => ['legacy_invoice_amount_unit_currency_and_status_unapproved'],
                'source_status' => $invoice->status,
            ];
            $unresolvedReceivableCount++;
        }

        $payables = $this->vendorPayables($asOf);
        $formattedTotals = [];
        foreach ($totals as $currency => $total) {
            $formattedTotals[$currency] = [
                'invoice_total' => $this->format($total['invoice_total']),
                'confirmed_allocated' => $this->format($total['confirmed_allocated']),
                'outstanding' => $this->format($total['outstanding']),
                'credit' => $this->format($total['credit']),
                'invoice_count' => $total['invoice_count'],
            ];
        }

        usort($receivables, static fn (array $left, array $right): int => [
            $left['due_date'] ?? '9999-12-31', $left['source_reference'],
        ] <=> [
            $right['due_date'] ?? '9999-12-31', $right['source_reference'],
        ]);

        return [
            'as_of' => $asOf->toDateString(),
            'timezone' => self::TIMEZONE,
            'generated_at' => CarbonImmutable::now(self::TIMEZONE)->toDateTimeString(),
            'receivables' => $receivables,
            'payables' => $payables,
            'receivable_totals' => $formattedTotals,
            'summary' => [
                'supported_receivables' => $supportedCount,
                'unresolved_receivables' => $unresolvedReceivableCount,
                'vendor_invoice_records' => count($payables),
                'payable_total_contract_approved' => false,
            ],
        ];
    }

    /**
     * @param  Collection<int, \stdClass>  $legacyInvoices
     * @param  Collection<int, \stdClass>  $allocations
     * @param  array<int, string>  $sourcePaymentTotals
     * @return array<string, mixed>
     */
    private function issuedInvoiceRow(
        \stdClass $invoice,
        CarbonImmutable $asOf,
        Collection $legacyInvoices,
        Collection $allocations,
        array $sourcePaymentTotals,
    ): array {
        $legacyId = $invoice->legacy_customer_invoice_id !== null ? (int) $invoice->legacy_customer_invoice_id : null;
        $invoiceAllocations = $legacyId === null
            ? collect()
            : $allocations->where('customer_invoice_id', $legacyId)->values();
        $base = [
            'source_type' => 'issued_customer_invoice',
            'source_id' => (int) $invoice->id,
            'source_reference' => $invoice->invoice_number ?: (string) $invoice->public_id,
            'legacy_customer_invoice_id' => $legacyId,
            'entity' => $invoice->buyer_name ?: 'Unknown customer',
            'document_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date,
            'currency_code' => $invoice->currency_code ?: 'Unknown',
            'invoice_total' => null,
            'confirmed_allocated' => null,
            'outstanding_amount' => null,
            'credit_amount' => null,
            'allocation_count' => $invoiceAllocations->where('status', PaymentAllocation::STATUS_CONFIRMED)->count(),
            'pending_allocation_count' => $invoiceAllocations->where('status', PaymentAllocation::STATUS_PROPOSED)->count(),
            'reversed_allocation_count' => $invoiceAllocations->where('status', PaymentAllocation::STATUS_REVERSED)->count(),
            'allocation_history' => $this->allocationHistory($invoiceAllocations),
            'balance_status' => 'needs_review',
            'payment_state' => 'unresolved',
            'aging_bucket' => 'unknown',
            'days_overdue' => null,
            'reason_codes' => [],
            'document_status' => $invoice->document_status,
        ];

        $legacy = $legacyId === null ? null : $legacyInvoices->get($legacyId);
        $reason = match (true) {
            $invoice->document_status !== 'issued' => 'issued_invoice_has_been_corrected',
            $invoice->document_type !== 'invoice' => 'credit_or_debit_note_requires_approved_netting_rule',
            blank($invoice->issued_snapshot_hash) => 'issued_snapshot_hash_missing',
            $invoice->gross_total === null || ! is_numeric($invoice->gross_total) || BigDecimal::of((string) $invoice->gross_total)->compareTo(BigDecimal::zero()) <= 0 => 'missing_or_nonpositive_gross_total',
            ! $this->validCurrency($invoice->currency_code) => 'missing_or_invalid_invoice_currency',
            $legacyId === null => 'missing_legacy_payment_allocation_link',
            $legacy === null => 'legacy_payment_allocation_target_missing',
            $legacy->deleted_at !== null => 'legacy_payment_allocation_target_deleted',
            ! $this->validCurrency($legacy->currency_code) => 'legacy_payment_target_currency_unresolved',
            strtoupper((string) $legacy->currency_code) !== strtoupper((string) $invoice->currency_code) => 'legacy_payment_target_currency_mismatch',
            default => null,
        };

        if ($reason !== null) {
            $base['reason_codes'] = [$reason];

            return $base;
        }

        $currency = strtoupper((string) $invoice->currency_code);
        $allocationSummary = $this->summarizeConfirmedAllocations($invoiceAllocations, $currency, 'money_in', $sourcePaymentTotals);
        if ($allocationSummary['reasons'] !== []) {
            $base['reason_codes'] = $allocationSummary['reasons'];

            return $base;
        }

        $invoiceTotal = BigDecimal::of((string) $invoice->gross_total)->toScale(2);
        $allocated = BigDecimal::of($allocationSummary['total']);
        $difference = $invoiceTotal->minus($allocated);
        $outstanding = $difference->compareTo(BigDecimal::zero()) > 0 ? $difference : BigDecimal::zero();
        $credit = $difference->compareTo(BigDecimal::zero()) < 0 ? $difference->negated() : BigDecimal::zero();
        $base['currency_code'] = $currency;
        $base['invoice_total'] = $this->format($invoiceTotal);
        $base['confirmed_allocated'] = $this->format($allocated);
        $base['outstanding_amount'] = $this->format($outstanding);
        $base['credit_amount'] = $this->format($credit);
        $base['balance_status'] = match (true) {
            $credit->compareTo(BigDecimal::zero()) > 0 => 'credit_balance',
            $outstanding->compareTo(BigDecimal::zero()) === 0 => 'fully_allocated_pending_settlement_confirmation',
            $allocated->compareTo(BigDecimal::zero()) > 0 => 'partially_allocated',
            default => 'open_balance',
        };
        $base['payment_state'] = $base['balance_status'];
        if ($outstanding->compareTo(BigDecimal::zero()) > 0) {
            [$base['aging_bucket'], $base['days_overdue']] = $this->aging($invoice->due_date, $asOf);
        } else {
            $base['aging_bucket'] = 'not_applicable';
        }

        return $base;
    }

    /**
     * @param  array<int, int>  $invoiceIds
     * @return Collection<int, \stdClass>
     */
    private function loadAllocations(string $invoiceColumn, array $invoiceIds): Collection
    {
        if ($invoiceIds === []) {
            return collect();
        }

        return DB::table('payment_allocations')
            ->join('transactions', 'transactions.id', '=', 'payment_allocations.transaction_id')
            ->whereIn('payment_allocations.'.$invoiceColumn, $invoiceIds)
            ->where('payment_allocations.allocation_kind', PaymentAllocation::KIND_INVOICE)
            ->select([
                'payment_allocations.id', 'payment_allocations.transaction_id',
                'payment_allocations.customer_invoice_id', 'payment_allocations.vendor_invoice_id',
                'payment_allocations.amount_decimal', 'payment_allocations.currency_code',
                'payment_allocations.status', 'payment_allocations.reason',
                'payment_allocations.confirmed_by', 'payment_allocations.confirmed_at',
                'payment_allocations.reversed_by', 'payment_allocations.reversed_at',
                'payment_allocations.reversal_reason',
                'transactions.amount_decimal as payment_amount',
                'transactions.currency_code as payment_currency',
                'transactions.transaction_type_code as payment_direction',
                'transactions.deleted_at as payment_deleted_at',
            ])
            ->orderBy('payment_allocations.id')
            ->get();
    }

    /**
     * @param  array<int, int>  $transactionIds
     * @return array<int, string>
     */
    private function loadSourcePaymentTotals(array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        return DB::table('payment_allocations')
            ->whereIn('transaction_id', $transactionIds)
            ->where('status', PaymentAllocation::STATUS_CONFIRMED)
            ->groupBy('transaction_id')
            ->pluck(DB::raw('SUM(amount_decimal)'), 'transaction_id')
            ->map(fn ($amount): string => (string) $amount)
            ->all();
    }

    /**
     * @param  Collection<int, \stdClass>  $allocations
     * @param  array<int, string>  $sourcePaymentTotals
     * @return array{total: string, reasons: array<int, string>}
     */
    private function summarizeConfirmedAllocations(
        Collection $allocations,
        string $invoiceCurrency,
        string $expectedDirection,
        array $sourcePaymentTotals,
    ): array {
        $confirmed = $allocations->where('status', PaymentAllocation::STATUS_CONFIRMED)->values();
        $duplicateCounts = $confirmed->countBy('transaction_id');
        $total = BigDecimal::zero();
        $reasons = [];

        foreach ($confirmed as $allocation) {
            if (($duplicateCounts[$allocation->transaction_id] ?? 0) > 1) {
                $reasons['duplicate_confirmed_payment_allocation'] = true;

                continue;
            }
            if (! $this->validCurrency($allocation->currency_code)
                || strtoupper((string) $allocation->currency_code) !== $invoiceCurrency) {
                $reasons['confirmed_allocation_currency_mismatch'] = true;

                continue;
            }
            if (! $this->validCurrency($allocation->payment_currency)
                || strtoupper((string) $allocation->payment_currency) !== $invoiceCurrency) {
                $reasons['source_payment_currency_mismatch'] = true;

                continue;
            }
            if ($allocation->payment_deleted_at !== null) {
                $reasons['source_payment_deleted'] = true;

                continue;
            }
            if ($allocation->payment_direction !== $expectedDirection) {
                $reasons['source_payment_direction_unresolved'] = true;

                continue;
            }
            if (! is_numeric($allocation->payment_amount)
                || BigDecimal::of((string) $allocation->payment_amount)->compareTo(BigDecimal::zero()) <= 0) {
                $reasons['source_payment_amount_unresolved'] = true;

                continue;
            }
            if (! is_numeric($allocation->amount_decimal)
                || BigDecimal::of((string) $allocation->amount_decimal)->compareTo(BigDecimal::zero()) <= 0) {
                $reasons['allocation_amount_invalid'] = true;

                continue;
            }
            $sourceTotal = BigDecimal::of($sourcePaymentTotals[$allocation->transaction_id] ?? '0');
            if ($sourceTotal->compareTo(BigDecimal::of((string) $allocation->payment_amount)) > 0) {
                $reasons['source_payment_overallocated'] = true;

                continue;
            }

            $total = $total->plus(BigDecimal::of((string) $allocation->amount_decimal));
        }

        return ['total' => $this->format($total), 'reasons' => array_keys($reasons)];
    }

    /**
     * @param  Collection<int, \stdClass>  $allocations
     * @return array<int, array<string, mixed>>
     */
    private function allocationHistory(Collection $allocations): array
    {
        return $allocations->map(fn (\stdClass $allocation): array => [
            'allocation_id' => (int) $allocation->id,
            'transaction_id' => (int) $allocation->transaction_id,
            'amount' => (string) $allocation->amount_decimal,
            'currency' => (string) $allocation->currency_code,
            'status' => (string) $allocation->status,
            'reason' => (string) $allocation->reason,
            'reversal_reason' => $allocation->reversal_reason,
            'confirmed_by' => $allocation->confirmed_by,
            'confirmed_at' => $allocation->confirmed_at,
            'reversed_by' => $allocation->reversed_by,
            'reversed_at' => $allocation->reversed_at,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vendorPayables(CarbonImmutable $asOf): array
    {
        $invoices = DB::table('vendor_invoices')
            ->leftJoin('vendors', 'vendors.id', '=', 'vendor_invoices.vendor_id')
            ->whereNull('vendor_invoices.deleted_at')
            ->select([
                'vendor_invoices.id', 'vendor_invoices.identifier', 'vendor_invoices.invoice_number',
                'vendor_invoices.workflow_status', 'vendor_invoices.payable_on',
                'vendor_invoices.currency_code', 'vendor_invoices.corrects_vendor_invoice_id',
                'vendors.name as vendor_name',
            ])
            ->orderBy('vendor_invoices.payable_on')
            ->orderBy('vendor_invoices.id')
            ->get();

        $ids = $invoices->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $allocations = $this->loadAllocations('vendor_invoice_id', $ids);
        $transactionIds = $allocations->pluck('transaction_id')->unique()->values()->all();
        $sourceTotals = $this->loadSourcePaymentTotals($transactionIds);
        $rows = [];

        foreach ($invoices as $invoice) {
            $invoiceAllocations = $allocations->where('vendor_invoice_id', $invoice->id)->values();
            $currency = strtoupper(trim((string) $invoice->currency_code));
            $currencyValid = $this->validCurrency($currency);
            $confirmed = $invoiceAllocations->where('status', PaymentAllocation::STATUS_CONFIRMED);
            $summary = $currencyValid
                ? $this->summarizeConfirmedAllocations($invoiceAllocations, $currency, 'money_out', $sourceTotals)
                : ['total' => '0.00', 'reasons' => $confirmed->isNotEmpty() ? ['vendor_invoice_currency_unresolved'] : []];
            $payable = $invoice->workflow_status === 'payable';
            [$agingBucket, $daysOverdue] = $payable
                ? $this->aging($invoice->payable_on, $asOf)
                : ['not_applicable', null];

            $rows[] = [
                'source_type' => 'vendor_invoice',
                'source_id' => (int) $invoice->id,
                'source_reference' => $invoice->invoice_number ?: ($invoice->identifier ?: 'Vendor invoice #'.$invoice->id),
                'entity' => $invoice->vendor_name ?: 'Unknown vendor',
                'workflow_status' => $invoice->workflow_status ?: 'unresolved',
                'due_date' => $invoice->payable_on,
                'currency_code' => $currencyValid ? $currency : 'Unknown',
                'payable_amount' => null,
                'confirmed_allocated' => $summary['reasons'] === [] && $currencyValid ? $summary['total'] : null,
                'outstanding_amount' => null,
                'allocation_count' => $confirmed->count(),
                'pending_allocation_count' => $invoiceAllocations->where('status', PaymentAllocation::STATUS_PROPOSED)->count(),
                'reversed_allocation_count' => $invoiceAllocations->where('status', PaymentAllocation::STATUS_REVERSED)->count(),
                'allocation_history' => $this->allocationHistory($invoiceAllocations),
                'payment_state' => $summary['reasons'] !== []
                    ? 'allocation_needs_review'
                    : ($summary['total'] === '0.00' ? 'no_confirmed_allocations_recorded' : 'confirmed_allocations_recorded_not_settlement'),
                'aging_bucket' => $agingBucket,
                'days_overdue' => $daysOverdue,
                'reason_codes' => array_values(array_unique(array_merge(
                    ['vendor_payable_total_not_approved'],
                    $summary['reasons'],
                    ! $currencyValid ? ['vendor_invoice_currency_unresolved'] : [],
                    $payable && $invoice->payable_on === null ? ['payable_date_missing'] : [],
                    $invoice->corrects_vendor_invoice_id !== null ? ['correction_relationship_requires_review'] : [],
                ))),
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: string, 1: ?int}
     */
    private function aging(?string $dueDate, CarbonImmutable $asOf): array
    {
        if ($dueDate === null || trim($dueDate) === '') {
            return ['due_date_unknown', null];
        }

        try {
            $due = CarbonImmutable::parse($dueDate, self::TIMEZONE)->startOfDay();
        } catch (\Throwable) {
            return ['due_date_invalid', null];
        }

        if ($due->greaterThan($asOf)) {
            return ['not_due', 0];
        }

        $days = (int) $due->diffInDays($asOf);
        if ($days === 0) {
            return ['due_today', 0];
        }

        return match (true) {
            $days <= 30 => ['overdue_1_30_days', $days],
            $days <= 60 => ['overdue_31_60_days', $days],
            $days <= 90 => ['overdue_61_90_days', $days],
            default => ['overdue_over_90_days', $days],
        };
    }

    private function validCurrency(?string $currency): bool
    {
        return is_string($currency) && preg_match('/\A[A-Z]{3}\z/', strtoupper(trim($currency))) === 1;
    }

    private function format(BigDecimal $amount): string
    {
        return (string) $amount->toScale(2);
    }
}
