<?php

namespace App\Services;

use App\Services\Finance\TransactionClassificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FinancialOverviewData
{
    /**
     * Summarize recorded transactions by their explicit type and linked account currency.
     * Invoice and product amounts are deliberately not combined because their schemas do
     * not consistently store currency or define a common amount unit.
     *
     * @return array{period: string, previous_period: string, transactions: array<int, array{currency: string, type: string, current_total: int, previous_total: int, current_count: int, previous_count: int}>, customer_invoice_statuses: array<int, array{status: string, count: int}>, vendor_invoice_statuses: array<int, array{status: string, currency: string, count: int}>, transaction_classifications: array<int, array{code: string, label: string, status: string, current_count: int, previous_count: int}>}
     */
    public function summary(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $start = $now->startOfMonth();
        $end = $now->endOfMonth();
        $previousStart = $start->subMonth();
        $previousEnd = $start->subMicrosecond();

        $currentTransactions = DB::table('transactions')
            ->join('financial_accounts', 'financial_accounts.id', '=', 'transactions.financial_account_id')
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.timestamp', [$start, $end])
            ->selectRaw('financial_accounts.currency as currency, transactions.transaction_type as type, SUM(transactions.amount) as total, COUNT(*) as record_count')
            ->groupBy('financial_accounts.currency', 'transactions.transaction_type')
            ->get()
            ->keyBy(fn (object $row): string => $this->transactionKey((string) $row->currency, (string) $row->type));

        $previousTransactions = DB::table('transactions')
            ->join('financial_accounts', 'financial_accounts.id', '=', 'transactions.financial_account_id')
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.timestamp', [$previousStart, $previousEnd])
            ->selectRaw('financial_accounts.currency as currency, transactions.transaction_type as type, SUM(transactions.amount) as total, COUNT(*) as record_count')
            ->groupBy('financial_accounts.currency', 'transactions.transaction_type')
            ->get()
            ->keyBy(fn (object $row): string => $this->transactionKey((string) $row->currency, (string) $row->type));

        $keys = $currentTransactions->keys()->merge($previousTransactions->keys())->unique();
        $transactions = $keys->map(function (string $key) use ($currentTransactions, $previousTransactions): array {
            $current = $currentTransactions->get($key);
            $previous = $previousTransactions->get($key);
            $row = $current ?? $previous;

            return [
                'currency' => (string) $row->currency,
                'type' => (string) $row->type,
                'current_total' => (int) ($current->total ?? 0),
                'previous_total' => (int) ($previous->total ?? 0),
                'current_count' => (int) ($current->record_count ?? 0),
                'previous_count' => (int) ($previous->record_count ?? 0),
            ];
        })->sortBy(fn (array $row): string => $row['currency'].' '.$row['type'])->values()->all();

        $currentClassifications = $this->classificationCounts($start, $end);
        $previousClassifications = $this->classificationCounts($previousStart, $previousEnd);
        $classificationCodes = array_unique(array_merge(array_keys($currentClassifications), array_keys($previousClassifications)));
        $transactionClassifications = [];
        foreach ($classificationCodes as $code) {
            $current = $currentClassifications[$code] ?? null;
            $previous = $previousClassifications[$code] ?? null;
            $classification = $current ?? $previous;
            $transactionClassifications[] = [
                'code' => $code,
                'label' => $classification['label'],
                'status' => $classification['status'],
                'current_count' => (int) ($current['count'] ?? 0),
                'previous_count' => (int) ($previous['count'] ?? 0),
            ];
        }
        usort($transactionClassifications, static fn (array $left, array $right): int => $left['label'] <=> $right['label']);

        $customerInvoiceStatuses = DB::table('customer_invoices')
            ->whereNull('deleted_at')
            ->whereBetween('month', [$start->toDateString(), $end->toDateString()])
            ->select('status')
            ->selectRaw('COUNT(*) as record_count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (object $row): array => ['status' => (string) $row->status, 'count' => (int) $row->record_count])
            ->all();

        $vendorInvoiceStatuses = DB::table('vendor_invoices')
            ->whereNull('deleted_at')
            ->whereBetween('month', [$start->toDateString(), $end->toDateString()])
            ->select('status', 'currency')
            ->selectRaw('COUNT(*) as record_count')
            ->groupBy('status', 'currency')
            ->orderBy('currency')
            ->orderBy('status')
            ->get()
            ->map(fn (object $row): array => [
                'status' => (string) $row->status,
                'currency' => (string) $row->currency,
                'count' => (int) $row->record_count,
            ])
            ->all();

        return [
            'period' => $now->format('F Y'),
            'previous_period' => $previousStart->format('F Y'),
            'transactions' => $transactions,
            'customer_invoice_statuses' => $customerInvoiceStatuses,
            'vendor_invoice_statuses' => $vendorInvoiceStatuses,
            'transaction_classifications' => $transactionClassifications,
        ];
    }

    /** @return array<string, array{label: string, status: string, count: int}> */
    private function classificationCounts(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::table('transactions')
            ->whereNull('deleted_at')
            ->whereBetween('timestamp', [$start, $end])
            ->select('transaction_type', 'transaction_type_code')
            ->selectRaw('COUNT(*) as record_count')
            ->groupBy('transaction_type', 'transaction_type_code')
            ->get();
        $classifier = app(TransactionClassificationService::class);
        $counts = [];
        foreach ($rows as $row) {
            $classification = $classifier->classify((string) $row->transaction_type, $row->transaction_type_code === null ? null : (string) $row->transaction_type_code);
            $code = $classification['code'];
            $counts[$code] ??= ['label' => $classification['label'], 'status' => $classification['status'], 'count' => 0];
            $counts[$code]['count'] += (int) $row->record_count;
        }

        return $counts;
    }

    private function transactionKey(string $currency, string $type): string
    {
        return $currency.'|'.$type;
    }
}
