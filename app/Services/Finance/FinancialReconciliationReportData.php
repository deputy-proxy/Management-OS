<?php

namespace App\Services\Finance;

use App\Models\BankStatementImportItem;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class FinancialReconciliationReportData
{
    private const DATE_TOLERANCE_DAYS = 3;

    /**
     * Read-only reconciliation diagnostics for completed statement imports.
     * Candidate matches are suggestions only and never mutate source records.
     *
     * @return array{from: string, to: string, generated_at: string, summary: array<string, int>, rows: array<int, array<string, mixed>>, currencies: array<int, string>, accounts: array<int, array{id: int, title: string}>}
     */
    public function report(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $accountId = null,
        ?string $currency = null,
        ?string $decision = null,
    ): array {
        $timezone = 'Europe/Bucharest';
        $from = $from->setTimezone($timezone)->startOfDay();
        $to = $to->setTimezone($timezone)->endOfDay();
        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }
        $currency = $currency !== null ? strtoupper(trim($currency)) : null;
        $currency = $currency === '' ? null : $currency;
        $decision = in_array($decision, ['unreviewed', 'exact_match', 'partial_candidate', 'unmatched', 'ambiguous', 'rejected'], true) ? $decision : null;

        $optionsQuery = DB::table('bank_statement_import_items as item')
            ->join('bank_statement_import_batches as batch', 'batch.id', '=', 'item.batch_id')
            ->join('financial_accounts as account', 'account.id', '=', 'item.financial_account_id')
            ->where('batch.status', 'completed')
            ->whereBetween('item.transaction_date', [$from->toDateString(), $to->toDateString()]);

        $baseQuery = (clone $optionsQuery)
            ->when($accountId !== null && $accountId > 0, fn ($query) => $query->where('item.financial_account_id', $accountId))
            ->when($currency !== null, fn ($query) => $query->where('item.currency_code', $currency));

        $sourceRows = (clone $baseQuery)->select([
            'item.id', 'item.batch_id', 'item.financial_account_id', 'item.transaction_date',
            'item.direction', 'item.amount_decimal', 'item.currency_code', 'item.description',
            'item.operation_reference', 'item.document_reference', 'item.source_page', 'item.source_row',
            'item.review_reasons', 'item.source_sha256', 'item.parser_version',
            'account.title as account_title', 'batch.bank_code', 'batch.statement_start', 'batch.statement_end',
        ])->orderBy('item.transaction_date')->orderBy('item.id')->limit(1000)->get();

        $itemIds = $sourceRows->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $accountIds = $sourceRows->pluck('financial_account_id')->unique()->map(fn ($id): int => (int) $id)->all();
        $transactions = $accountIds === [] ? collect() : DB::table('transactions')
            ->whereNull('deleted_at')
            ->whereIn('financial_account_id', $accountIds)
            ->whereBetween('timestamp', [$from->subDays(self::DATE_TOLERANCE_DAYS), $to->addDays(self::DATE_TOLERANCE_DAYS)])
            ->whereNotNull('amount_decimal')
            ->whereNotNull('currency_code')
            ->whereIn('transaction_type_code', ['money_in', 'money_out'])
            ->select(['id', 'financial_account_id', 'timestamp', 'transaction_type_code', 'amount_decimal', 'currency_code'])
            ->orderBy('timestamp')->orderBy('id')->get();

        $transactionsByAccount = $transactions->groupBy('financial_account_id');
        $activities = $itemIds === [] ? collect() : Activity::query()
            ->with('causer')
            ->where('subject_type', (new BankStatementImportItem)->getMorphClass())
            ->whereIn('subject_id', $itemIds)
            ->where('event', 'manual_match_decision')
            ->orderBy('id')
            ->get()
            ->groupBy('subject_id');

        $allocationCounts = [];
        $transactionIds = $transactions->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($transactionIds !== []) {
            foreach (DB::table('payment_allocations')
                ->whereIn('transaction_id', $transactionIds)
                ->select(['transaction_id', 'status'])
                ->selectRaw('COUNT(*) AS row_count')
                ->groupBy('transaction_id', 'status')
                ->get() as $allocation) {
                $allocationCounts[(int) $allocation->transaction_id][(string) $allocation->status] = (int) $allocation->row_count;
            }
        }

        $rows = [];
        foreach ($sourceRows as $source) {
            $history = ($activities->get($source->id) ?? collect())->map(fn (Activity $activity): array => [
                'outcome' => (string) $activity->getProperty('outcome'),
                'target_type' => $activity->getProperty('target_type'),
                'target_id' => $activity->getProperty('target_id') !== null ? (int) $activity->getProperty('target_id') : null,
                'reason' => (string) $activity->getProperty('reason'),
                'actor' => $activity->causer?->getAttribute('name'),
                'recorded_at' => $activity->created_at?->toDateTimeString(),
            ])->values()->all();
            $latest = $history === [] ? null : $history[array_key_last($history)];
            $matches = $this->candidates($source, $transactionsByAccount->get($source->financial_account_id, collect()));
            foreach ($matches['candidates'] as &$candidate) {
                $candidate['allocation_counts'] = array_merge(
                    ['proposed' => 0, 'confirmed' => 0, 'reversed' => 0, 'rejected' => 0],
                    $allocationCounts[$candidate['transaction_id']] ?? [],
                );
            }
            unset($candidate);
            $status = $latest['outcome'] ?? ($matches['status'] === 'ambiguous' ? 'ambiguous' : 'unreviewed');
            if ($decision !== null && $status !== $decision) {
                continue;
            }

            $rows[] = [
                'id' => (int) $source->id,
                'batch_id' => (int) $source->batch_id,
                'account_id' => (int) $source->financial_account_id,
                'account' => (string) $source->account_title,
                'bank' => (string) $source->bank_code,
                'date' => (string) $source->transaction_date,
                'direction' => (string) $source->direction,
                'amount' => (string) $source->amount_decimal,
                'currency' => (string) $source->currency_code,
                'description' => (string) $source->description,
                'operation_reference' => $source->operation_reference,
                'document_reference' => $source->document_reference,
                'source_page' => (int) $source->source_page,
                'source_row' => (int) $source->source_row,
                'source_sha256' => (string) $source->source_sha256,
                'parser_version' => (string) $source->parser_version,
                'review_reasons' => is_array($source->review_reasons) ? $source->review_reasons : (json_decode((string) $source->review_reasons, true) ?: []),
                'status' => $status,
                'decision' => $latest,
                'history' => $history,
                'candidate_status' => $matches['status'],
                'candidates' => $matches['candidates'],
                'candidate_count' => count($matches['candidates']),
                'candidate_rule' => 'Same account, currency and direction; exact/near-date candidates require the same amount, while partial candidates have a smaller amount. Date window: '.self::DATE_TOLERANCE_DAYS.' calendar days. Suggestions only, not an approved automatic matching rule.',
            ];
        }

        $candidateUse = [];
        foreach ($rows as $row) {
            foreach ($row['candidates'] as $candidate) {
                $candidateUse[$candidate['transaction_id']] = ($candidateUse[$candidate['transaction_id']] ?? 0) + 1;
            }
        }
        foreach ($rows as &$row) {
            foreach ($row['candidates'] as &$candidate) {
                $candidate['shared_candidate_count'] = $candidateUse[$candidate['transaction_id']] ?? 1;
            }
            unset($candidate);
            if ($row['decision'] === null && $row['candidate_status'] === 'ambiguous') {
                $row['status'] = 'ambiguous';
            }
        }
        unset($row);

        $summary = [
            'statement_rows' => count($rows),
            'exact_match' => 0,
            'partial_candidate' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
            'rejected' => 0,
            'unreviewed' => 0,
            'exact_candidates' => 0,
            'near_candidates' => 0,
            'partial_candidates' => 0,
        ];
        foreach ($rows as $row) {
            $summary[$row['status']] = ($summary[$row['status']] ?? 0) + 1;
            $summary['exact_candidates'] += count(array_filter($row['candidates'], static fn (array $candidate): bool => $candidate['match_type'] === 'exact'));
            $summary['near_candidates'] += count(array_filter($row['candidates'], static fn (array $candidate): bool => $candidate['match_type'] === 'near_date'));
            $summary['partial_candidates'] += count(array_filter($row['candidates'], static fn (array $candidate): bool => $candidate['match_type'] === 'partial_amount'));
        }

        $accounts = (clone $optionsQuery)->select(['account.id', 'account.title'])
            ->distinct()->orderBy('account.title')->get()
            ->map(fn (object $account): array => ['id' => (int) $account->id, 'title' => (string) $account->title])->all();
        $currencies = (clone $optionsQuery)->select('item.currency_code')->distinct()->orderBy('item.currency_code')->pluck('currency_code')->map(fn ($value): string => (string) $value)->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'generated_at' => CarbonImmutable::now($timezone)->toDateTimeString(),
            'summary' => $summary,
            'rows' => $rows,
            'accounts' => $accounts,
            'currencies' => $currencies,
        ];
    }

    /**
     * @param  \stdClass  $source
     * @param  Collection<int, \stdClass>  $transactions
     * @return array{status: string, candidates: array<int, array{transaction_id: int, date: string, amount: string, currency: string, direction: string, match_type: string, allocation_counts: array<string, int>}>}
     */
    private function candidates(object $source, Collection $transactions): array
    {
        $exact = [];
        $near = [];
        $partial = [];
        $sourceDate = CarbonImmutable::parse((string) $source->transaction_date, 'Europe/Bucharest');
        foreach ($transactions as $transaction) {
            if ($transaction->currency_code !== $source->currency_code
                || $transaction->transaction_type_code !== $source->direction) {
                continue;
            }
            $transactionDate = CarbonImmutable::parse((string) $transaction->timestamp, 'Europe/Bucharest');
            $days = abs((int) $sourceDate->diffInDays($transactionDate));
            if ($days > self::DATE_TOLERANCE_DAYS) {
                continue;
            }
            $amountComparison = BigDecimal::of((string) $transaction->amount_decimal)->compareTo(BigDecimal::of((string) $source->amount_decimal));
            if ($amountComparison === 0 && $days === 0) {
                $exact[] = $this->candidate($transaction, 'exact');
            } elseif ($amountComparison === 0) {
                $near[] = $this->candidate($transaction, 'near_date');
            } elseif ($amountComparison < 0) {
                $partial[] = $this->candidate($transaction, 'partial_amount');
            }
        }

        $candidates = $exact !== [] ? $exact : ($near !== [] ? $near : $partial);
        $status = count($candidates) > 1
            ? 'ambiguous'
            : (count($exact) === 1 ? 'exact_candidate' : (count($near) === 1 ? 'near_candidate' : (count($partial) === 1 ? 'partial_candidate' : 'no_candidate')));

        return ['status' => $status, 'candidates' => $candidates];
    }

    /** @return array{transaction_id: int, date: string, amount: string, currency: string, direction: string, match_type: string, allocation_counts: array<string, int>} */
    private function candidate(object $transaction, string $matchType): array
    {
        return [
            'transaction_id' => (int) $transaction->id,
            'date' => substr((string) $transaction->timestamp, 0, 10),
            'amount' => (string) $transaction->amount_decimal,
            'currency' => (string) $transaction->currency_code,
            'direction' => (string) $transaction->transaction_type_code,
            'match_type' => $matchType,
            'allocation_counts' => ['proposed' => 0, 'confirmed' => 0, 'reversed' => 0, 'rejected' => 0],
        ];
    }
}
