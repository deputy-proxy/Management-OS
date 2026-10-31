<?php

namespace App\Services;

use App\Services\Finance\TransactionClassificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ConsolidatedFinancialReportData
{
    /**
     * Build a currency-safe operational activity report.
     *
     * Issued customer documents and canonical cash movements have explicit,
     * separate bases. Legacy vendor invoice amounts are disclosed as
     * unavailable where their canonical payable total is not approved.
     *
     * @return array{from: string, to: string, timezone: string, groups: array<int, array<string, mixed>>, records: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function report(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $currency = null,
        string $basis = 'all',
    ): array {
        $timezone = 'Europe/Bucharest';
        $start = $start->setTimezone($timezone)->startOfDay();
        $end = $end->setTimezone($timezone)->endOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $currency = $currency !== null ? strtoupper(trim($currency)) : null;
        $currency = $currency === '' ? null : $currency;

        $records = [];
        $totals = ['included' => 0, 'excluded' => 0, 'unknown' => 0];

        if (in_array($basis, ['all', 'customer_documents'], true)) {
            $documents = DB::table('issued_invoices')
                ->join('legal_entities', 'legal_entities.id', '=', 'issued_invoices.legal_entity_id')
                ->whereIn('issued_invoices.document_status', ['issued', 'corrected'])
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('issued_invoices.issue_date', [$start->toDateString(), $end->toDateString()])
                        ->orWhereNull('issued_invoices.issue_date');
                })
                ->select([
                    'issued_invoices.id', 'issued_invoices.document_type', 'issued_invoices.issue_date',
                    'issued_invoices.currency_code', 'issued_invoices.gross_total',
                    'issued_invoices.invoice_number', 'issued_invoices.public_id',
                    'issued_invoices.corrects_issued_invoice_id', 'issued_invoices.document_status',
                    'legal_entities.legal_name as entity',
                ])
                ->orderBy('issued_invoices.issue_date')
                ->orderBy('issued_invoices.id')
                ->get();

            foreach ($documents as $document) {
                $currencyCode = $document->currency_code;
                $validCurrency = is_string($currencyCode) && preg_match('/\A[A-Z]{3}\z/', $currencyCode) === 1;
                if ($currency !== null && $currencyCode !== $currency) {
                    continue;
                }

                $amountAvailable = $validCurrency && $document->gross_total !== null;
                $records[] = [
                    'period' => $document->issue_date ? substr((string) $document->issue_date, 0, 7) : 'Undated',
                    'basis' => 'issued_customer_document',
                    'category' => (string) $document->document_type,
                    'entity' => (string) $document->entity,
                    'currency' => $validCurrency ? $currencyCode : 'Unknown',
                    'amount' => $amountAvailable ? (string) $document->gross_total : null,
                    'source_type' => 'issued_invoice',
                    'source_id' => (int) $document->id,
                    'source_reference' => $document->invoice_number ?: $document->public_id,
                    'source_url' => null,
                    'source_date' => $document->issue_date,
                    'status' => $amountAvailable ? 'included' : 'unknown',
                    'reason' => $amountAvailable ? null : (! $validCurrency ? 'missing_or_invalid_currency' : 'missing_gross_total'),
                    'correction_of' => $document->corrects_issued_invoice_id,
                    'document_status' => $document->document_status,
                ];
            }
        }

        if (in_array($basis, ['all', 'customer_documents'], true)) {
            $legacyCustomerInvoices = DB::table('customer_invoices')
                ->whereNull('deleted_at')
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('month', [$start->toDateString(), $end->toDateString()])
                        ->orWhereNull('month');
                })
                ->select(['id', 'customer_id', 'month', 'status', 'status_code'])
                ->orderBy('month')
                ->orderBy('id')
                ->get();

            foreach ($legacyCustomerInvoices as $invoice) {
                if ($currency !== null) {
                    continue;
                }

                $records[] = [
                    'period' => $invoice->month ? substr((string) $invoice->month, 0, 7) : 'Undated',
                    'basis' => 'legacy_customer_invoice_record',
                    'category' => 'customer_invoice',
                    'entity' => 'Customer #'.$invoice->customer_id,
                    'currency' => 'Unknown',
                    'amount' => null,
                    'source_type' => 'customer_invoice',
                    'source_id' => (int) $invoice->id,
                    'source_reference' => 'Customer invoice #'.$invoice->id,
                    'source_url' => route('filament.admin.finance.resources.customer-invoices.view', ['record' => $invoice->id]),
                    'source_date' => $invoice->month,
                    'status' => 'unknown',
                    'reason' => 'legacy_amount_unit_and_currency_unresolved',
                    'source_status' => $invoice->status_code ?: $invoice->status,
                ];
            }
        }

        if (in_array($basis, ['all', 'vendor_invoices'], true)) {
            $vendorInvoices = DB::table('vendor_invoices')
                ->leftJoin('vendors', 'vendors.id', '=', 'vendor_invoices.vendor_id')
                ->whereNull('vendor_invoices.deleted_at')
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('vendor_invoices.month', [$start->toDateString(), $end->toDateString()])
                        ->orWhereNull('vendor_invoices.month');
                })
                ->select([
                    'vendor_invoices.id', 'vendor_invoices.month', 'vendor_invoices.currency_code',
                    'vendor_invoices.status', 'vendor_invoices.status_code', 'vendors.name as entity',
                ])
                ->orderBy('vendor_invoices.month')
                ->orderBy('vendor_invoices.id')
                ->get();

            foreach ($vendorInvoices as $invoice) {
                if ($currency !== null && $invoice->currency_code !== $currency) {
                    continue;
                }
                $validCurrency = is_string($invoice->currency_code) && preg_match('/\A[A-Z]{3}\z/', $invoice->currency_code) === 1;
                $records[] = [
                    'period' => $invoice->month ? substr((string) $invoice->month, 0, 7) : 'Undated',
                    'basis' => 'vendor_invoice_record',
                    'category' => 'vendor_invoice',
                    'entity' => $invoice->entity ?: 'Unknown vendor',
                    'currency' => $validCurrency ? $invoice->currency_code : 'Unknown',
                    'amount' => null,
                    'source_type' => 'vendor_invoice',
                    'source_id' => (int) $invoice->id,
                    'source_reference' => 'Vendor invoice #'.$invoice->id,
                    'source_url' => route('filament.admin.finance.resources.vendor-invoices.view', ['record' => $invoice->id]),
                    'source_date' => $invoice->month,
                    'status' => 'excluded',
                    'reason' => 'vendor_payable_total_not_approved',
                    'source_status' => $invoice->status_code ?: $invoice->status,
                ];
            }
        }

        if (in_array($basis, ['all', 'cash_movements'], true)) {
            $transactions = DB::table('transactions')
                ->leftJoin('financial_accounts', 'financial_accounts.id', '=', 'transactions.financial_account_id')
                ->whereNull('transactions.deleted_at')
                ->whereBetween('transactions.timestamp', [$start, $end])
                ->select([
                    'transactions.id', 'transactions.timestamp', 'transactions.transaction_type',
                    'transactions.transaction_type_code', 'transactions.amount_decimal',
                    'transactions.currency_code', 'financial_accounts.title as entity',
                ])
                ->orderBy('transactions.timestamp')
                ->orderBy('transactions.id')
                ->get();

            foreach ($transactions as $transaction) {
                $validCurrency = is_string($transaction->currency_code) && preg_match('/\A[A-Z]{3}\z/', $transaction->currency_code) === 1;
                if ($currency !== null && $transaction->currency_code !== $currency) {
                    continue;
                }
                $classification = app(TransactionClassificationService::class)->classify(
                    $transaction->transaction_type,
                    $transaction->transaction_type_code,
                );
                $validDirection = in_array($transaction->transaction_type_code, ['money_in', 'money_out'], true);
                $validClassification = $classification['status'] === 'classified';
                $validAmount = $transaction->amount_decimal !== null && is_numeric($transaction->amount_decimal) && bccomp((string) $transaction->amount_decimal, '0', 2) >= 0;
                $included = $validCurrency && $validDirection && $validAmount && $validClassification;
                $records[] = [
                    'period' => substr((string) $transaction->timestamp, 0, 7),
                    'basis' => 'canonical_cash_movement',
                    'category' => $classification['code'],
                    'entity' => $transaction->entity ?: 'Unknown financial account',
                    'currency' => $validCurrency ? $transaction->currency_code : 'Unknown',
                    'amount' => $included ? (string) $transaction->amount_decimal : null,
                    'source_type' => 'transaction',
                    'source_id' => (int) $transaction->id,
                    'source_reference' => 'Transaction #'.$transaction->id,
                    'source_url' => route('filament.admin.finance.resources.transactions.view', ['record' => $transaction->id]),
                    'source_date' => $transaction->timestamp,
                    'status' => $included ? 'included' : 'unknown',
                    'reason' => $included ? null : (! $validDirection ? 'missing_or_unsupported_direction' : (! $validClassification ? 'unclassified_transaction_type_or_direction' : (! $validAmount ? 'missing_or_invalid_canonical_amount' : 'missing_or_invalid_currency'))),
                    'classification_label' => $classification['label'],
                    'direction' => $classification['direction'],
                    'legacy_type' => $transaction->transaction_type,
                ];
            }
        }

        $groups = [];
        foreach ($records as $record) {
            $key = implode('|', [
                $record['period'], $record['basis'], $record['category'],
                $record['entity'], $record['currency'],
            ]);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'period' => $record['period'],
                    'basis' => $record['basis'],
                    'category' => $record['category'],
                    'entity' => $record['entity'],
                    'currency' => $record['currency'],
                    'amount' => '0.00',
                    'included_count' => 0,
                    'excluded_count' => 0,
                    'unknown_count' => 0,
                    'source_ids' => [],
                    'sources' => [],
                    'reasons' => [],
                ];
            }

            $groups[$key]['source_ids'][] = $record['source_type'].':'.$record['source_id'];
            $groups[$key]['sources'][] = ['label' => $record['source_reference'], 'url' => $record['source_url']];
            if ($record['status'] === 'included' && $record['amount'] !== null) {
                $groups[$key]['amount'] = bcadd($groups[$key]['amount'], $record['amount'], 2);
                $groups[$key]['included_count']++;
                $totals['included']++;
            } elseif ($record['status'] === 'excluded') {
                $groups[$key]['excluded_count']++;
                $groups[$key]['reasons'][$record['reason']] = true;
                $totals['excluded']++;
            } else {
                $groups[$key]['unknown_count']++;
                $groups[$key]['reasons'][$record['reason']] = true;
                $totals['unknown']++;
            }
        }

        foreach ($groups as &$group) {
            $group['reasons'] = array_keys($group['reasons']);
            $group['amount'] = $group['included_count'] > 0 ? $group['amount'] : null;
        }
        unset($group);

        $groups = array_values($groups);
        usort($groups, static function (array $left, array $right): int {
            return strcmp($right['period'], $left['period'])
                ?: strcmp($left['basis'], $right['basis'])
                ?: strcmp($left['category'], $right['category'])
                ?: strcmp($left['entity'], $right['entity'])
                ?: strcmp($left['currency'], $right['currency']);
        });

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'timezone' => $timezone,
            'groups' => $groups,
            'records' => $records,
            'totals' => $totals,
        ];
    }
}
