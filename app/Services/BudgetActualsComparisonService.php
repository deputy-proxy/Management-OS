<?php

namespace App\Services;

use App\Models\BudgetActualReportRun;
use App\Models\BudgetVersion;
use App\Models\Transaction;
use App\Services\Finance\TransactionClassificationService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BudgetActualsComparisonService
{
    /**
     * Compare one immutable budget version with explicitly classified cash movements.
     *
     * This is a cash-movement view, not recognized revenue or accrual accounting.
     * Transaction classifications must match budget line codes exactly. Transactions
     * have no dimensional attribution contract, so dimensioned lines remain unknown.
     *
     * @param  array<string, string>  $dimensions
     * @return array<string, mixed>
     */
    public function compare(
        BudgetVersion $version,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        ?string $currencyCode = null,
        array $dimensions = [],
    ): array {
        $version->loadMissing('lineItems');
        $start = Carbon::parse($periodStart ?? $version->period_start)->startOfDay();
        $end = Carbon::parse($periodEnd ?? $version->period_end)->endOfDay();

        if ($end->lt($start)
            || $start->toDateString() < $version->period_start->toDateString()
            || $end->toDateString() > $version->period_end->toDateString()) {
            throw ValidationException::withMessages([
                'period_end' => 'The reporting period must be within the selected budget version period.',
            ]);
        }

        $currency = strtoupper($currencyCode ?? $version->currency_code);
        if ($currency !== $version->currency_code) {
            throw ValidationException::withMessages([
                'currency_code' => 'Actuals are compared only in the selected budget version currency. FX conversion is not configured.',
            ]);
        }

        foreach ($dimensions as $key => $value) {
            if (! array_key_exists($key, $version->dimensions ?? []) || ($version->dimensions[$key] ?? null) !== $value) {
                throw ValidationException::withMessages([
                    'dimensions' => 'Selected dimensions must match the selected budget version dimension contract.',
                ]);
            }
        }

        $lines = $version->lineItems
            ->filter(function ($line) use ($dimensions): bool {
                foreach ($dimensions as $key => $value) {
                    if (($line->dimensions[$key] ?? null) !== $value) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $codeCounts = $lines->pluck('code')->filter()->countBy()->all();
        $mappedCodes = array_keys(array_filter($codeCounts, fn (int $count): bool => $count === 1));
        $transactions = Transaction::query()
            ->whereBetween('timestamp', [$start, $end])
            ->orderBy('id')
            ->get();

        $classifier = app(TransactionClassificationService::class);
        $actuals = [];
        $sources = [];
        $excluded = [];
        $excludedKnownAmounts = BigDecimal::zero();
        $excludedKnownAmountCount = 0;

        foreach ($transactions as $transaction) {
            $classification = $classifier->classify(
                $transaction->transaction_type,
                $transaction->transaction_type_code,
            );
            $amount = $transaction->amount_decimal;
            $transactionCurrency = strtoupper((string) $transaction->currency_code);

            if ($classification['status'] !== 'classified'
                || $amount === null
                || preg_match('/^[A-Z]{3}$/', $transactionCurrency) !== 1) {
                $excluded[] = [
                    'source_type' => 'transaction',
                    'source_id' => (int) $transaction->getKey(),
                    'reason' => 'Classification, canonical decimal amount, and valid currency are required.',
                    'amount' => $amount,
                    'currency' => preg_match('/^[A-Z]{3}$/', $transactionCurrency) === 1 ? $transactionCurrency : null,
                ];
                if ($amount !== null && $transactionCurrency === $currency) {
                    $excludedKnownAmounts = $excludedKnownAmounts->plus((string) $amount);
                    $excludedKnownAmountCount++;
                }

                continue;
            }

            if ($transactionCurrency !== $currency) {
                $excluded[] = [
                    'source_type' => 'transaction',
                    'source_id' => (int) $transaction->getKey(),
                    'reason' => 'Currency differs from the selected budget version; no FX conversion is applied.',
                    'amount' => (string) $amount,
                    'currency' => $transactionCurrency,
                ];

                continue;
            }

            $code = $classification['code'];
            if (($codeCounts[$code] ?? 0) > 1) {
                $excluded[] = [
                    'source_type' => 'transaction',
                    'source_id' => (int) $transaction->getKey(),
                    'reason' => 'The classification code matches multiple budget lines and cannot be allocated without an explicit dimension mapping.',
                    'amount' => (string) $amount,
                    'currency' => $transactionCurrency,
                    'classification_code' => $code,
                ];

                continue;
            }

            if (! in_array($code, $mappedCodes, true)) {
                $excluded[] = [
                    'source_type' => 'transaction',
                    'source_id' => (int) $transaction->getKey(),
                    'reason' => 'No budget line has an exact matching classification code.',
                    'amount' => (string) $amount,
                    'currency' => $transactionCurrency,
                    'classification_code' => $code,
                ];

                continue;
            }

            $actuals[$code] ??= BigDecimal::zero();
            $actuals[$code] = $actuals[$code]->plus((string) $amount);
            $sources[$code][] = (int) $transaction->getKey();
        }

        $rows = $lines->map(function ($line) use ($actuals, $sources, $codeCounts): array {
            $budget = BigDecimal::of((string) $line->amount);
            $dimensioned = ($line->dimensions ?? []) !== [];
            $ambiguous = filled($line->code) && ($codeCounts[$line->code] ?? 0) > 1;
            $mapped = filled($line->code) && ! $dimensioned && ! $ambiguous && array_key_exists($line->code, $actuals);
            $actual = $dimensioned ? null : ($mapped ? $actuals[$line->code] : null);
            $variance = $actual instanceof BigDecimal ? $actual->minus($budget) : null;
            $variancePercent = $variance !== null && ! $budget->isZero()
                ? $variance->multipliedBy(100)->dividedBy($budget, 2, RoundingMode::HALF_UP)->__toString()
                : null;

            return [
                'line_item_id' => (int) $line->getKey(),
                'code' => $line->code,
                'description' => $line->description,
                'dimensions' => $line->dimensions ?? [],
                'budget_amount' => $budget->toScale(2, RoundingMode::HALF_UP)->__toString(),
                'actual_amount' => $actual?->toScale(2, RoundingMode::HALF_UP)->__toString(),
                'variance_amount' => $variance?->toScale(2, RoundingMode::HALF_UP)->__toString(),
                'variance_percent' => $variancePercent,
                'status' => $dimensioned ? 'unknown_dimension_attribution' : ($ambiguous ? 'ambiguous_budget_code' : ($mapped ? 'matched' : 'missing_actuals')),
                'source_transaction_ids' => $sources[$line->code] ?? [],
            ];
        })->all();

        $report = [
            'budget_version_id' => (int) $version->getKey(),
            'budget_id' => (int) $version->budget_id,
            'budget_version' => (int) $version->version,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'currency_code' => $currency,
            'dimensions' => $dimensions,
            'actuals_basis' => 'classified_transaction_cash_movements_v1',
            'actuals_basis_label' => 'Classified cash movements only; variance is actual minus budget. Not recognized revenue, accrual accounting, or reconciled actuals.',
            'rows' => $rows,
            'excluded' => $excluded,
            'excluded_known_amount' => $excludedKnownAmountCount > 0
                ? $excludedKnownAmounts->toScale(2, RoundingMode::HALF_UP)->__toString()
                : null,
            'excluded_known_amount_count' => $excludedKnownAmountCount,
            'generated_at' => now()->toIso8601String(),
        ];

        $snapshot = BudgetActualReportRun::query()->create([
            'budget_version_id' => $version->getKey(),
            'created_by' => auth()->id(),
            'period_start' => $report['period_start'],
            'period_end' => $report['period_end'],
            'currency_code' => $currency,
            'actuals_basis' => $report['actuals_basis'],
            'dimensions' => $dimensions,
            'report_snapshot' => $report,
            'created_at' => now(),
        ]);

        $report['report_run_id'] = (int) $snapshot->getKey();

        return $report;
    }
}