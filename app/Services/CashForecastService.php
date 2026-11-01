<?php

namespace App\Services;

use App\Models\CashForecast;
use App\Models\CashForecastEntry;
use App\Models\CashForecastRun;
use App\Models\CashForecastScenario;
use App\Services\Finance\ReceivablesPayablesData;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashForecastService
{
    private const TIMEZONE = 'Europe/Bucharest';

    /** @param array<string, mixed> $data */
    public function create(array $data): CashForecastScenario
    {
        $this->authorize('cash-forecast.create');
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => 'Forecast name is required and must be at most 160 characters.']);
        }

        return DB::transaction(function () use ($data, $name): CashForecastScenario {
            $forecast = CashForecast::query()->create(['name' => $name, 'created_by' => auth()->id()]);

            return $this->createScenarioVersion($forecast, $data, 1);
        });
    }

    /** @param array<string, mixed> $data */
    public function createVersion(CashForecastScenario $source, array $data = []): CashForecastScenario
    {
        $this->authorize('cash-forecast.update');

        return DB::transaction(function () use ($source, $data): CashForecastScenario {
            $source = CashForecastScenario::query()->with('entries', 'forecast')->lockForUpdate()->findOrFail($source->getKey());
            $latestScenario = CashForecastScenario::query()->where('cash_forecast_id', $source->cash_forecast_id)
                ->where('scenario_name', $source->scenario_name)->lockForUpdate()->orderByDesc('version')->first();
            if ($latestScenario && $latestScenario->status === CashForecastScenario::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Finish the current draft before creating another scenario version.']);
            }
            $latest = $latestScenario->version ?? 0;
            $payload = array_replace([
                'scenario_name' => $source->scenario_name,
                'period_start' => $source->period_start->toDateString(),
                'period_end' => $source->period_end->toDateString(),
                'currency_code' => $source->currency_code,
                'opening_balance' => $source->opening_balance,
                'assumptions' => $source->assumptions['notes'] ?? '',
                'entries' => $source->entries->map(fn (CashForecastEntry $entry): array => [
                    'direction' => $entry->direction, 'description' => $entry->description,
                    'expected_on' => $entry->expected_on->toDateString(), 'amount' => $entry->amount,
                    'currency_code' => $entry->currency_code, 'confidence' => $entry->confidence, 'status' => $entry->status,
                    'source_reference' => $entry->source_reference,
                ])->all(),
            ], $data);

            return $this->createScenarioVersion($source->forecast, $payload, (int) $latest + 1);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(CashForecastScenario $scenario, array $data): CashForecastScenario
    {
        $this->authorize('cash-forecast.update');

        return DB::transaction(function () use ($scenario, $data): CashForecastScenario {
            $scenario = CashForecastScenario::query()->lockForUpdate()->findOrFail($scenario->getKey());
            if ($scenario->status !== CashForecastScenario::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only draft scenarios can be edited. Create a new version instead.']);
            }
            $normalized = $this->normalize($data);
            $scenario->update([
                'scenario_name' => $normalized['scenario_name'], 'period_start' => $normalized['period_start'],
                'period_end' => $normalized['period_end'], 'currency_code' => $normalized['currency_code'],
                'opening_balance' => $normalized['opening_balance'], 'assumptions' => ['notes' => $normalized['assumptions']],
            ]);
            $scenario->entries()->delete();
            $this->createEntries($scenario, $normalized['entries']);

            return $scenario->refresh()->load('forecast', 'entries');
        });
    }

    public function approve(CashForecastScenario $scenario): CashForecastScenario
    {
        $this->authorize('cash-forecast.approve');

        return DB::transaction(function () use ($scenario): CashForecastScenario {
            $scenario = CashForecastScenario::query()->lockForUpdate()->findOrFail($scenario->getKey());
            if ($scenario->status !== CashForecastScenario::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only draft scenarios can be approved.']);
            }
            $scenario->status = CashForecastScenario::STATUS_APPROVED;
            $scenario->approved_by = auth()->id();
            $scenario->approved_at = now();
            $scenario->save();

            return $scenario->refresh()->load('forecast', 'entries');
        });
    }

    public function generateRun(CashForecastScenario $scenario, ?CarbonImmutable $asOf = null): CashForecastRun
    {
        $this->authorize('cash-forecast.view');
        $asOf = ($asOf ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE)->startOfDay();

        return DB::transaction(function () use ($scenario, $asOf): CashForecastRun {
            $scenario = CashForecastScenario::query()->with('forecast', 'entries')
                ->lockForUpdate()->findOrFail($scenario->getKey());
            $snapshot = $this->report($scenario, $asOf);

            return CashForecastRun::query()->create([
                'cash_forecast_scenario_id' => $scenario->getKey(),
                'as_of' => $asOf->toDateString(),
                'report_snapshot' => $snapshot,
                'generated_by' => auth()->id(),
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function report(CashForecastScenario $scenario, ?CarbonImmutable $asOf = null): array
    {
        $this->authorize('cash-forecast.view');
        $scenario->loadMissing('forecast', 'entries');
        $asOf = ($asOf ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE)->startOfDay();
        $start = CarbonImmutable::parse($scenario->period_start->toDateString(), self::TIMEZONE);
        $end = CarbonImmutable::parse($scenario->period_end->toDateString(), self::TIMEZONE);
        $currency = $scenario->currency_code;
        $actuals = DB::table('transactions')->whereNull('deleted_at')
            ->whereBetween('timestamp', [$start->startOfDay()->toDateTimeString(), min($end->endOfDay()->toDateTimeString(), $asOf->endOfDay()->toDateTimeString())])
            ->whereIn('transaction_type_code', ['money_in', 'money_out'])->where('currency_code', $currency)
            ->where('amount_decimal', '>', 0)->orderBy('timestamp')->orderBy('id')
            ->get(['id', 'timestamp', 'transaction_type_code', 'amount_decimal']);

        $daily = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $daily[$date->toDateString()] = [
                'actual_inflows' => BigDecimal::zero(), 'actual_outflows' => BigDecimal::zero(),
                'projected_inflows' => BigDecimal::zero(), 'projected_outflows' => BigDecimal::zero(),
                'actual_transaction_ids' => [], 'projected_sources' => [],
            ];
        }
        foreach ($actuals as $transaction) {
            $date = CarbonImmutable::parse((string) $transaction->timestamp, self::TIMEZONE)->toDateString();
            if (! isset($daily[$date])) {
                continue;
            }
            $key = $transaction->transaction_type_code === 'money_in' ? 'actual_inflows' : 'actual_outflows';
            $daily[$date][$key] = $daily[$date][$key]->plus(BigDecimal::of((string) $transaction->amount_decimal));
            $daily[$date]['actual_transaction_ids'][] = (int) $transaction->id;
        }

        $sourceReport = app(ReceivablesPayablesData::class)->report($asOf);
        $receivableSources = [];
        $unresolvedReceivablePaymentLinks = 0;
        foreach ($sourceReport['receivables'] as $receivable) {
            $dueDate = $this->dateOnly($receivable['due_date']);
            if ($receivable['balance_status'] === 'needs_review' || ! is_string($receivable['outstanding_amount'])
                || $receivable['currency_code'] !== $currency || $dueDate === null
                || $dueDate <= $asOf->toDateString() || ! isset($daily[$dueDate])) {
                continue;
            }
            $legacyInvoiceId = $receivable['legacy_customer_invoice_id'] ?? null;
            if ($legacyInvoiceId !== null) {
                $confirmedPaymentIds = collect($receivable['allocation_history'])
                    ->where('effective_status', 'confirmed')->pluck('transaction_id')->unique()->all();
                $unallocatedLinkedPayments = DB::table('transactions')->whereNull('deleted_at')
                    ->where('customer_invoice_id', $legacyInvoiceId)
                    ->where('timestamp', '<=', $asOf->endOfDay()->toDateTimeString())
                    ->when($confirmedPaymentIds === [], fn ($query) => $query, fn ($query) => $query->whereNotIn('id', $confirmedPaymentIds))
                    ->count();
                if ($unallocatedLinkedPayments > 0) {
                    $unresolvedReceivablePaymentLinks += $unallocatedLinkedPayments;

                    continue;
                }
            }
            $amount = BigDecimal::of($receivable['outstanding_amount']);
            if ($amount->compareTo(BigDecimal::zero()) <= 0) {
                continue;
            }
            $daily[$dueDate]['projected_inflows'] = $daily[$dueDate]['projected_inflows']->plus($amount);
            $daily[$dueDate]['projected_sources'][] = [
                'type' => 'supported_receivable', 'reference' => (string) $receivable['source_reference'],
                'amount' => $this->format($amount), 'confidence' => 'medium', 'status' => 'assumed',
                'basis' => 'Outstanding issued invoice balance; due date is an unconfirmed expected receipt date.',
            ];
            $receivableSources[] = (string) $receivable['source_reference'];
        }

        $expiredAssumptions = 0;
        $duplicateManualAssumptions = 0;
        foreach ($scenario->entries as $entry) {
            $date = $entry->expected_on->toDateString();
            if ($date <= $asOf->toDateString()) {
                $expiredAssumptions++;

                continue;
            }
            if ($entry->direction === 'inflow' && $entry->source_reference !== null
                && in_array($entry->source_reference, $receivableSources, true)) {
                $duplicateManualAssumptions++;

                continue;
            }
            $key = $entry->direction === 'inflow' ? 'projected_inflows' : 'projected_outflows';
            $amount = BigDecimal::of((string) $entry->amount);
            $daily[$date][$key] = $daily[$date][$key]->plus($amount);
            $daily[$date]['projected_sources'][] = [
                'type' => 'manual_assumption', 'reference' => $entry->source_reference ?: $entry->description,
                'amount' => $this->format($amount), 'confidence' => $entry->confidence, 'status' => $entry->status, 'basis' => $entry->description,
            ];
        }

        $balance = BigDecimal::of((string) $scenario->opening_balance);
        $rows = [];
        $totals = array_fill_keys(['actual_inflows', 'actual_outflows', 'projected_inflows', 'projected_outflows'], BigDecimal::zero());
        foreach ($daily as $date => $values) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] = $totals[$key]->plus($values[$key]);
            }
            $net = $values['actual_inflows']->minus($values['actual_outflows'])
                ->plus($values['projected_inflows'])->minus($values['projected_outflows']);
            $balance = $balance->plus($net);
            $rows[] = [
                'date' => $date, 'actual_inflows' => $this->format($values['actual_inflows']),
                'actual_outflows' => $this->format($values['actual_outflows']),
                'projected_inflows' => $this->format($values['projected_inflows']),
                'projected_outflows' => $this->format($values['projected_outflows']),
                'closing_balance' => $this->format($balance),
                'actual_transaction_ids' => $values['actual_transaction_ids'], 'projected_sources' => $values['projected_sources'],
            ];
        }

        $unresolvedPayables = 0;
        $undatedPayables = 0;
        foreach ($sourceReport['payables'] as $payable) {
            if ($payable['payable_amount'] !== null || $payable['currency_code'] !== $currency) {
                continue;
            }
            $dueDate = $this->dateOnly($payable['due_date']);
            if ($dueDate === null) {
                $undatedPayables++;
            } elseif ($dueDate > $asOf->toDateString()
                && $dueDate >= $start->toDateString() && $dueDate <= $end->toDateString()) {
                $unresolvedPayables++;
            }
        }
        $unknownActuals = DB::table('transactions')->whereNull('deleted_at')
            ->whereBetween('timestamp', [$start->startOfDay()->toDateTimeString(), min($end->endOfDay()->toDateTimeString(), $asOf->endOfDay()->toDateTimeString())])
            ->whereIn('transaction_type_code', ['money_in', 'money_out'])
            ->where(function ($query): void {
                $query->whereNull('amount_decimal')->orWhere('amount_decimal', '<=', 0)
                    ->orWhereNull('currency_code')->orWhere('currency_code', 'not like', '___');
            })->count();
        $otherCurrencyActuals = DB::table('transactions')->whereNull('deleted_at')
            ->whereBetween('timestamp', [$start->startOfDay()->toDateTimeString(), min($end->endOfDay()->toDateTimeString(), $asOf->endOfDay()->toDateTimeString())])
            ->whereIn('transaction_type_code', ['money_in', 'money_out'])->whereNotNull('currency_code')
            ->where('currency_code', '!=', $currency)->where('amount_decimal', '>', 0)->count();

        return [
            'forecast_name' => $scenario->forecast->name, 'scenario_name' => $scenario->scenario_name,
            'version' => $scenario->version, 'status' => $scenario->status,
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
            'currency_code' => $currency, 'opening_balance' => $this->format(BigDecimal::of((string) $scenario->opening_balance)),
            'closing_balance' => $this->format($balance), 'as_of' => $asOf->toDateString(),
            'assumptions' => $scenario->assumptions['notes'] ?? '', 'rows' => $rows,
            'manual_assumption_count' => $scenario->entries->count(),
            'totals' => array_map(fn (BigDecimal $amount): string => $this->format($amount), $totals),
            'sources' => [
                'supported_receivable_count' => count($receivableSources), 'supported_receivables' => $receivableSources,
                'unresolved_receivable_payment_links' => $unresolvedReceivablePaymentLinks,
                'payables_unavailable_count' => $unresolvedPayables, 'undated_payables_missing_amount_count' => $undatedPayables,
                'unknown_actual_transaction_count' => $unknownActuals,
                'other_currency_actual_transaction_count' => $otherCurrencyActuals,
                'expired_manual_assumption_count' => $expiredAssumptions,
                'duplicate_manual_assumption_count' => $duplicateManualAssumptions,
                'actual_basis' => 'Canonical transaction amount_decimal and currency_code by transaction timestamp and money_in/money_out direction.',
                'receivable_basis' => 'Only supported issued-invoice outstanding balances without unmatched linked cash transactions; invoice due date is an assumption, not a promised collection date.',
                'payable_basis' => 'Vendor payable totals are not approved by the current source contract. No amount is inferred; add a manual outflow assumption when verified.',
                'currency_basis' => 'Currencies are kept separate. No foreign-exchange conversion is configured.',
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private function createScenarioVersion(CashForecast $forecast, array $data, int $version): CashForecastScenario
    {
        $normalized = $this->normalize($data);
        $scenario = $forecast->scenarios()->create([
            'scenario_name' => $normalized['scenario_name'], 'version' => $version,
            'period_start' => $normalized['period_start'], 'period_end' => $normalized['period_end'],
            'currency_code' => $normalized['currency_code'], 'opening_balance' => $normalized['opening_balance'],
            'assumptions' => ['notes' => $normalized['assumptions']], 'status' => CashForecastScenario::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]);
        $this->createEntries($scenario, $normalized['entries']);

        return $scenario->load('forecast', 'entries');
    }

    /** @param array<int, array<string, string|null>> $entries */
    private function createEntries(CashForecastScenario $scenario, array $entries): void
    {
        foreach ($entries as $entry) {
            $scenario->entries()->create($entry);
        }
    }

    /** @param array<string, mixed> $data
     * @return array{scenario_name: string, period_start: string, period_end: string, currency_code: string, opening_balance: string, assumptions: string, entries: array<int, array<string, string|null>>}
     */
    private function normalize(array $data): array
    {
        $name = trim((string) ($data['scenario_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['scenario_name' => 'Scenario name is required and must be at most 120 characters.']);
        }
        $start = $this->validDate($data['period_start'] ?? null, 'period_start');
        $end = $this->validDate($data['period_end'] ?? null, 'period_end');
        if ($end < $start || CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) > 730) {
            throw ValidationException::withMessages(['period_end' => 'Forecast period must be ordered and no longer than 731 calendar days.']);
        }
        $currency = strtoupper(trim((string) ($data['currency_code'] ?? '')));
        if (! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw ValidationException::withMessages(['currency_code' => 'Currency must be an uppercase three-letter code.']);
        }
        $opening = trim((string) ($data['opening_balance'] ?? ''));
        if (! preg_match('/^-?\d{1,17}(?:\.\d{1,2})?$/D', $opening)) {
            throw ValidationException::withMessages(['opening_balance' => 'Opening balance must be a decimal amount with up to two places.']);
        }
        $assumptions = trim((string) ($data['assumptions'] ?? ''));
        if (mb_strlen($assumptions) > 4000) {
            throw ValidationException::withMessages(['assumptions' => 'Assumptions must be at most 4,000 characters.']);
        }
        $entries = $data['entries'] ?? [];
        if (! is_array($entries) || count($entries) > 500) {
            throw ValidationException::withMessages(['entries' => 'Provide no more than 500 forecast entries.']);
        }
        $normalizedEntries = [];
        $sourceReferences = [];
        foreach (array_values($entries) as $index => $entry) {
            if (! is_array($entry)) {
                throw ValidationException::withMessages(["entries.{$index}" => 'Each forecast entry must be an object.']);
            }
            $direction = $entry['direction'] ?? '';
            if (! in_array($direction, ['inflow', 'outflow'], true)) {
                throw ValidationException::withMessages(["entries.{$index}.direction" => 'Choose inflow or outflow.']);
            }
            $description = trim((string) ($entry['description'] ?? ''));
            if ($description === '' || mb_strlen($description) > 200) {
                throw ValidationException::withMessages(["entries.{$index}.description" => 'Description is required and must be at most 200 characters.']);
            }
            $date = $this->validDate($entry['expected_on'] ?? null, "entries.{$index}.expected_on");
            if ($date < $start || $date > $end) {
                throw ValidationException::withMessages(["entries.{$index}.expected_on" => 'Entry date must fall within the forecast period.']);
            }
            $amount = trim((string) ($entry['amount'] ?? ''));
            if (! preg_match('/^\d{1,17}(?:\.\d{1,2})?$/D', $amount) || BigDecimal::of($amount)->compareTo(BigDecimal::zero()) <= 0) {
                throw ValidationException::withMessages(["entries.{$index}.amount" => 'Entry amount must be positive with up to two decimal places.']);
            }
            $entryCurrency = strtoupper(trim((string) ($entry['currency_code'] ?? $currency)));
            if ($entryCurrency !== $currency) {
                throw ValidationException::withMessages(["entries.{$index}.currency_code" => 'Entry currency must match the scenario currency. FX conversion is not configured.']);
            }
            $confidence = $entry['confidence'] ?? 'medium';
            if (! in_array($confidence, ['low', 'medium', 'high', 'unknown'], true)) {
                throw ValidationException::withMessages(["entries.{$index}.confidence" => 'Choose low, medium, high, or unknown confidence.']);
            }
            $status = $entry['status'] ?? 'assumed';
            if (! in_array($status, ['assumed', 'confirmed', 'at_risk', 'unknown'], true)) {
                throw ValidationException::withMessages(["entries.{$index}.status" => 'Choose assumed, confirmed, at risk, or unknown.']);
            }
            $reference = trim((string) ($entry['source_reference'] ?? ''));
            if (mb_strlen($reference) > 160) {
                throw ValidationException::withMessages(["entries.{$index}.source_reference" => 'Source reference must be at most 160 characters.']);
            }
            if ($reference !== '' && in_array($reference, $sourceReferences, true)) {
                throw ValidationException::withMessages(["entries.{$index}.source_reference" => 'Each manual cash-flow source reference must be unique within the scenario.']);
            }
            if ($reference !== '') {
                $sourceReferences[] = $reference;
            }
            $normalizedEntries[] = [
                'direction' => $direction, 'description' => $description, 'expected_on' => $date,
                'amount' => BigDecimal::of($amount)->toScale(2)->__toString(), 'currency_code' => $currency,
                'confidence' => $confidence, 'status' => $status, 'source_reference' => $reference === '' ? null : $reference,
            ];
        }

        return [
            'scenario_name' => $name, 'period_start' => $start, 'period_end' => $end,
            'currency_code' => $currency, 'opening_balance' => BigDecimal::of($opening)->toScale(2)->__toString(),
            'assumptions' => $assumptions, 'entries' => $normalizedEntries,
        ];
    }

    private function validDate(mixed $value, string $field): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw ValidationException::withMessages([$field => 'Enter a valid calendar date.']);
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, self::TIMEZONE);
        } catch (\Throwable) {
            $date = null;
        }
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Enter a valid calendar date.']);
        }

        return $value;
    }

    private function dateOnly(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value, self::TIMEZONE)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function format(BigDecimal $amount): string
    {
        return $amount->toScale(2)->__toString();
    }

    private function authorize(string $ability): void
    {
        if (! hexa()->can($ability)) {
            throw new AuthorizationException("The {$ability} permission is required.");
        }
    }
}
