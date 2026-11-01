<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAcquisitionCostEntry;
use App\Models\CustomerLead;
use App\Models\CustomerLeadAttributionEvent;
use App\Models\CustomerRetentionObservation;
use App\Models\CustomerUnitEconomicsResult;
use App\Models\CustomerUnitEconomicsRun;
use App\Models\ProductRevenue;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerUnitEconomicsService
{
    /**
     * Record a sourced acquisition cost. Null source_category deliberately means
     * shared/unattributed cost and is never silently distributed across channels.
     *
     * @param  array{incurred_on:string,source_category?:string|null,campaign_name?:string|null,amount_decimal:string|int,currency_code:string,source_reference:string,evidence_reference:string}  $attributes
     */
    public function recordAcquisitionCost(array $attributes): CustomerAcquisitionCostEntry
    {
        $currency = strtoupper(trim($attributes['currency_code']));
        $amount = (string) $attributes['amount_decimal'];

        if (! Carbon::hasFormat($attributes['incurred_on'], 'Y-m-d')
            || preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', $amount) !== 1
            || BigDecimal::of($amount)->compareTo(BigDecimal::zero()) <= 0
            || preg_match('/^[A-Z]{3}$/', $currency) !== 1
            || blank($attributes['source_reference'])
            || blank($attributes['evidence_reference'])
            || mb_strlen($attributes['source_reference']) > 512
            || mb_strlen($attributes['evidence_reference']) > 512) {
            throw ValidationException::withMessages([
                'amount_decimal' => 'Provide a positive amount, ISO currency, valid date, source reference, and evidence reference.',
            ]);
        }

        $category = $attributes['source_category'] ?? null;
        if ($category !== null && (! array_key_exists($category, CustomerLead::sourceCategories()) || $category === 'unknown')) {
            throw ValidationException::withMessages(['source_category' => 'Choose a supported lead source category or leave the cost shared/unattributed.']);
        }

        return CustomerAcquisitionCostEntry::query()->create([
            'incurred_on' => $attributes['incurred_on'],
            'source_category' => $category,
            'campaign_name' => $attributes['campaign_name'] ?? null,
            'amount_decimal' => BigDecimal::of($amount)->toScale(2, RoundingMode::HALF_UP)->__toString(),
            'currency_code' => $currency,
            'source_reference' => trim($attributes['source_reference']),
            'evidence_reference' => trim($attributes['evidence_reference']),
            'recorded_by' => auth()->id(),
        ]);
    }

    /**
     * Record an explicit, append-only retention observation. The observation is
     * evidence supplied by an authorized operator, not an inference from inactivity.
     *
     * @param  array{customer_id:int,observed_on:string,state:string,source_reference:string,evidence_reference:string}  $attributes
     */
    public function recordRetentionObservation(array $attributes): CustomerRetentionObservation
    {
        $customerId = (int) $attributes['customer_id'];
        $state = $attributes['state'];

        if (! Customer::withTrashed()->whereKey($customerId)->exists()
            || ! Carbon::hasFormat($attributes['observed_on'], 'Y-m-d')
            || ! in_array($state, config('customer-unit-economics.retention_states', []), true)
            || blank($attributes['source_reference'])
            || blank($attributes['evidence_reference'])
            || mb_strlen($attributes['source_reference']) > 512
            || mb_strlen($attributes['evidence_reference']) > 512) {
            throw ValidationException::withMessages([
                'state' => 'Provide an existing customer, valid observation date and state, source reference, and evidence reference.',
            ]);
        }

        return CustomerRetentionObservation::query()->create([
            'customer_id' => $customerId,
            'observed_on' => $attributes['observed_on'],
            'state' => $state,
            'policy_version' => (string) config('customer-unit-economics.retention_policy_version', 'unapproved'),
            'source_reference' => trim($attributes['source_reference']),
            'evidence_reference' => trim($attributes['evidence_reference']),
            'recorded_by' => auth()->id(),
        ]);
    }

    public function calculate(string $periodStart, string $periodEnd): CustomerUnitEconomicsRun
    {
        $timezone = (string) config('customer-unit-economics.timezone', 'Europe/Bucharest');
        $from = Carbon::createFromFormat('!Y-m-d', $periodStart, $timezone);
        $to = Carbon::createFromFormat('!Y-m-d', $periodEnd, $timezone);

        if (! $from || ! $to || $from->gt($to)) {
            throw ValidationException::withMessages([
                'period_end' => 'The reporting end date must be on or after the start date.',
            ]);
        }

        return DB::transaction(function () use ($from, $to): CustomerUnitEconomicsRun {
            /** @var list<array<string, mixed>> $exceptions */
            $exceptions = [];
            $leads = CustomerLead::withTrashed()
                ->where('status', 'converted')
                ->whereNotNull('converted_customer_id')
                ->whereBetween('converted_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->orderBy('converted_at')
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            $conversionEvents = CustomerLeadAttributionEvent::query()
                ->where('event_type', 'conversion')
                ->whereIn('customer_lead_id', $leads->keys())
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get();

            $acquisitionPolicyReady = config('customer-unit-economics.attribution_model') === config('customer-unit-economics.supported_attribution_model')
                && config('customer-unit-economics.deduplication') === config('customer-unit-economics.supported_deduplication');
            $cacPolicyReady = $acquisitionPolicyReady
                && config('customer-unit-economics.cac_basis') === config('customer-unit-economics.supported_cac_basis');
            $retentionPolicyVersion = config('customer-unit-economics.retention_policy_version');
            $retentionPolicyReady = config('customer-unit-economics.retention_basis') === config('customer-unit-economics.supported_retention_basis')
                && is_string($retentionPolicyVersion)
                && $retentionPolicyVersion !== ''
                && $retentionPolicyVersion !== 'unapproved';
            $ltvPolicyReady = $acquisitionPolicyReady
                && config('customer-unit-economics.revenue_basis') === config('customer-unit-economics.supported_revenue_basis')
                && config('customer-unit-economics.ltv_window') === config('customer-unit-economics.supported_ltv_window')
                && $retentionPolicyReady;

            if (! $acquisitionPolicyReady) {
                $exceptions[] = ['type' => 'acquisition_attribution_policy_unapproved'];
            }
            if (! $cacPolicyReady) {
                $exceptions[] = ['type' => 'cac_cost_policy_unapproved'];
            }
            if (! $retentionPolicyReady) {
                $exceptions[] = ['type' => 'retention_policy_unapproved'];
            }
            if (! $ltvPolicyReady) {
                $exceptions[] = ['type' => 'ltv_revenue_policy_unapproved'];
            }

            /** @var array<string, array<int, array{customer_id:int,lead_id:int,converted_at:string,event_id:int}>> $customersByCategory */
            $customersByCategory = [];
            /** @var array<int, array{customer_id:int,lead_id:int,converted_at:string,event_id:int}> $seenCustomers */
            $seenCustomers = [];
            /** @var array<int, int> $processedCustomers */
            $processedCustomers = [];
            $eventLeadIds = $conversionEvents->pluck('customer_lead_id')->map(fn ($id): int => (int) $id)->unique()->all();
            foreach ($leads as $lead) {
                if (! in_array((int) $lead->getKey(), $eventLeadIds, true)) {
                    $exceptions[] = ['type' => 'missing_conversion_attribution_event', 'lead_id' => (int) $lead->getKey()];
                }
            }

            foreach ($conversionEvents as $event) {
                $lead = $leads->get($event->customer_lead_id);
                $decodedSnapshot = json_decode((string) $event->getRawOriginal('snapshot'), true);
                $snapshot = is_array($decodedSnapshot) ? $decodedSnapshot : [];
                $customerId = (int) data_get($snapshot, 'conversion.customer_id', 0);
                if (! $lead || $customerId < 1 || $customerId !== (int) $lead->converted_customer_id
                    || ! Customer::withTrashed()->whereKey($customerId)->exists()) {
                    $exceptions[] = [
                        'type' => 'missing_or_mismatched_conversion_link',
                        'lead_id' => $event->customer_lead_id,
                        'event_id' => $event->getKey(),
                    ];

                    continue;
                }

                if (! $acquisitionPolicyReady) {
                    $exceptions[] = [
                        'type' => 'customer_excluded_pending_attribution_policy',
                        'customer_id' => $customerId,
                        'lead_id' => $lead->getKey(),
                    ];

                    continue;
                }

                if (isset($processedCustomers[$customerId])) {
                    $exceptions[] = [
                        'type' => 'duplicate_converted_lead',
                        'customer_id' => $customerId,
                        'excluded_lead_id' => $lead->getKey(),
                        'retained_lead_id' => $processedCustomers[$customerId],
                    ];

                    continue;
                }
                $processedCustomers[$customerId] = (int) $lead->getKey();

                $firstTouch = data_get($snapshot, 'first_touch');
                $category = is_array($firstTouch) ? ($firstTouch['source_category'] ?? null) : null;
                if (! is_string($category) || ! array_key_exists($category, CustomerLead::sourceCategories()) || $category === 'unknown') {
                    $exceptions[] = [
                        'type' => 'unknown_or_missing_acquisition_attribution',
                        'customer_id' => $customerId,
                        'lead_id' => $lead->getKey(),
                        'event_id' => $event->getKey(),
                    ];

                    continue;
                }

                $eventOccurredAt = Carbon::parse((string) $event->occurred_at, $from->getTimezone())->toISOString();
                $convertedAt = data_get($snapshot, 'conversion.converted_at') ?? $eventOccurredAt;
                if (! is_string($convertedAt) || Carbon::parse($convertedAt, $from->getTimezone())->lt($from) || Carbon::parse($convertedAt, $from->getTimezone())->gt($to->copy()->endOfDay())) {
                    continue;
                }

                $seenCustomers[$customerId] = [
                    'customer_id' => $customerId,
                    'lead_id' => (int) $lead->getKey(),
                    'converted_at' => Carbon::parse($convertedAt, $from->getTimezone())->toDateString(),
                    'event_id' => (int) $event->getKey(),
                ];
                $customersByCategory[$category][$customerId] = $seenCustomers[$customerId];
            }

            /** @var array<string, array<string, array{amount:BigDecimal,ids:list<int>,references:list<array{source_reference:string,evidence_reference:string}>}>> $costsByCategory */
            $costsByCategory = [];
            /** @var list<int> $sharedCostIds */
            $sharedCostIds = [];
            foreach (CustomerAcquisitionCostEntry::query()
                ->whereBetween('incurred_on', [$from->toDateString(), $to->toDateString()])
                ->orderBy('id')
                ->get() as $cost) {
                $currency = strtoupper((string) $cost->currency_code);
                if ($cost->source_category === null) {
                    $sharedCostIds[] = (int) $cost->getKey();
                    $exceptions[] = [
                        'type' => 'shared_or_unattributed_acquisition_cost',
                        'cost_entry_id' => $cost->getKey(),
                        'amount' => (string) $cost->amount_decimal,
                        'currency' => $currency,
                        'reason' => 'Shared costs are visible but excluded until an explicit source allocation exists.',
                    ];

                    continue;
                }

                $costsByCategory[$cost->source_category][$currency] ??= [
                    'amount' => BigDecimal::zero(),
                    'ids' => [],
                    'references' => [],
                ];
                $costsByCategory[$cost->source_category][$currency]['amount'] =
                    $costsByCategory[$cost->source_category][$currency]['amount']->plus((string) $cost->amount_decimal);
                $costsByCategory[$cost->source_category][$currency]['ids'][] = (int) $cost->getKey();
                $costsByCategory[$cost->source_category][$currency]['references'][] = [
                    'source_reference' => $cost->source_reference,
                    'evidence_reference' => $cost->evidence_reference,
                ];
            }

            /** @var array<string, array<int, BigDecimal>> $revenueByCustomerCurrency */
            $revenueByCustomerCurrency = [];
            /** @var array<string, array<int, list<int>>> $revenueIdsByCustomerCurrency */
            $revenueIdsByCustomerCurrency = [];
            /** @var array<int, string> $customerToCategory */
            $customerToCategory = [];
            foreach ($customersByCategory as $category => $customers) {
                foreach ($customers as $customerId => $customer) {
                    $customerToCategory[$customerId] = $category;
                }
            }

            /** @var array<int, array{observation_id:int,state:string,cutoff_date:string}> $retentionByCustomer */
            $retentionByCustomer = [];
            if ($retentionPolicyReady) {
                $cohortCustomerIds = array_map('intval', array_keys($customerToCategory));
                $observationsByCustomer = CustomerRetentionObservation::query()
                    ->whereIn('customer_id', $cohortCustomerIds)
                    ->whereDate('observed_on', '<=', $to->toDateString())
                    ->orderBy('observed_on')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('customer_id');

                foreach ($customerToCategory as $customerId => $category) {
                    $observations = $observationsByCustomer->get($customerId);
                    $observation = $observations?->last();
                    if (! $observation) {
                        $exceptions[] = ['type' => 'missing_retention_observation', 'customer_id' => $customerId];

                        continue;
                    }

                    $observedOn = substr((string) $observation->getRawOriginal('observed_on'), 0, 10);
                    $acquiredOn = $seenCustomers[$customerId]['converted_at'];
                    if ($observation->state === 'reactivated') {
                        $exceptions[] = ['type' => 'reactivation_policy_unapproved', 'customer_id' => $customerId, 'observation_id' => $observation->getKey()];

                        continue;
                    }

                    if ($observation->policy_version !== config('customer-unit-economics.retention_policy_version')
                        || $observation->state === 'unknown'
                        || ! in_array($observation->state, ['retained', 'churned'], true)
                        || $observedOn < $acquiredOn) {
                        $exceptions[] = [
                            'type' => 'unusable_retention_observation',
                            'customer_id' => $customerId,
                            'observation_id' => $observation->getKey(),
                            'state' => $observation->state,
                            'policy_version' => $observation->policy_version,
                        ];

                        continue;
                    }

                    $retentionByCustomer[$customerId] = [
                        'observation_id' => (int) $observation->getKey(),
                        'state' => $observation->state,
                        'cutoff_date' => $observedOn,
                    ];
                }
            }

            $revenues = $ltvPolicyReady
                ? ProductRevenue::query()
                    ->with('customerInvoice')
                    ->whereDate('month', '>=', $from->toDateString())
                    ->whereDate('month', '<=', $to->toDateString())
                    ->orderBy('id')
                    ->get()
                : collect();

            foreach ($revenues as $revenue) {
                $invoice = $revenue->customerInvoice;
                if (! $invoice) {
                    $exceptions[] = [
                        'type' => 'revenue_missing_invoice_link',
                        'revenue_id' => $revenue->getKey(),
                    ];

                    continue;
                }

                $customerId = (int) $invoice->customer_id;
                $category = $customerToCategory[$customerId] ?? null;
                if ($category === null) {
                    continue;
                }

                $acquiredAt = $seenCustomers[$customerId]['converted_at'];
                $retention = $retentionByCustomer[$customerId] ?? null;
                if (! $retention) {
                    continue;
                }
                $revenueMonth = substr((string) $revenue->getRawOriginal('month'), 0, 10);
                if (! $revenueMonth || $revenueMonth < $acquiredAt || $revenueMonth > $retention['cutoff_date']) {
                    continue;
                }

                $currency = strtoupper((string) $revenue->currency_code);
                if ($revenue->amount_decimal === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                    $exceptions[] = [
                        'type' => 'unknown_lifetime_revenue_input',
                        'revenue_id' => $revenue->getKey(),
                        'customer_id' => $customerId,
                        'currency' => preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null,
                    ];

                    continue;
                }

                $key = $category.'|'.$currency;
                $revenueByCustomerCurrency[$key][$customerId] ??= BigDecimal::zero();
                $revenueByCustomerCurrency[$key][$customerId] =
                    $revenueByCustomerCurrency[$key][$customerId]->plus((string) $revenue->amount_decimal);
                $revenueIdsByCustomerCurrency[$key][$customerId][] = (int) $revenue->getKey();
            }

            /** @var list<string> $categories */
            $categories = array_values(array_unique(array_merge(array_keys($customersByCategory), array_keys($costsByCategory))));
            $run = CustomerUnitEconomicsRun::query()->create([
                'run_id' => (string) Str::uuid(),
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'policy_version' => (string) config('customer-unit-economics.policy_version'),
                'policy_snapshot' => config('customer-unit-economics'),
                'exceptions' => $exceptions,
            ]);

            foreach ($categories as $category) {
                $customerRows = $customersByCategory[$category] ?? [];
                $customerIds = array_map('intval', array_keys($customerRows));
                /** @var list<string> $currencies */
                $currencies = array_values(array_unique(array_merge(
                    array_keys($costsByCategory[$category] ?? []),
                    array_map(
                        fn (string $key): string => substr($key, strlen($category) + 1),
                        array_filter(array_keys($revenueByCustomerCurrency), fn (string $key): bool => str_starts_with($key, $category.'|')),
                    ),
                )));

                foreach ($currencies as $currency) {
                    $cost = $costsByCategory[$category][$currency] ?? null;
                    $costAmount = $cost['amount'] ?? null;
                    $cac = $cacPolicyReady && $costAmount !== null && count($customerIds) > 0
                        ? $costAmount->dividedBy(count($customerIds), 2, RoundingMode::HALF_UP)
                        : null;

                    $revenueKey = $category.'|'.$currency;
                    $customerRevenue = $revenueByCustomerCurrency[$revenueKey] ?? [];
                    $revenueCoverage = count($customerRevenue);
                    $revenueTotal = $revenueCoverage > 0
                        ? array_reduce($customerRevenue, fn (BigDecimal $total, BigDecimal $amount): BigDecimal => $total->plus($amount), BigDecimal::zero())
                        : null;
                    $retentionCoverage = count(array_intersect($customerIds, array_keys($retentionByCustomer)));
                    $ltv = $ltvPolicyReady && count($customerIds) > 0 && $retentionCoverage === count($customerIds) && $revenueCoverage === count($customerIds) && $revenueTotal !== null
                        ? $revenueTotal->dividedBy(count($customerIds), 2, RoundingMode::HALF_UP)
                        : null;

                    $revenueIds = [];
                    foreach ($revenueIdsByCustomerCurrency[$revenueKey] ?? [] as $ids) {
                        $revenueIds = array_merge($revenueIds, $ids);
                    }

                    CustomerUnitEconomicsResult::query()->create([
                        'customer_unit_economics_run_id' => $run->getKey(),
                        'source_category' => $category,
                        'currency_code' => $currency,
                        'acquired_customer_count' => count($customerIds),
                        'cost_entry_count' => count($cost['ids'] ?? []),
                        'acquisition_cost_amount' => $costAmount?->toScale(2, RoundingMode::HALF_UP)->__toString(),
                        'cac_amount' => $cac?->toScale(2, RoundingMode::HALF_UP)->__toString(),
                        'revenue_amount' => $revenueTotal?->toScale(2, RoundingMode::HALF_UP)->__toString(),
                        'ltv_amount' => $ltv?->toScale(2, RoundingMode::HALF_UP)->__toString(),
                        'revenue_coverage_count' => $revenueCoverage,
                        'revenue_unknown_count' => max(0, count($customerIds) - $revenueCoverage),
                        'retention_coverage_count' => $retentionCoverage,
                        'retention_unknown_count' => max(0, count($customerIds) - $retentionCoverage),
                        'result_status' => $cac !== null && $ltv !== null ? 'complete' : 'incomplete',
                        'source_snapshot' => [
                            'customer_ids' => $customerIds,
                            'lead_ids' => array_values(array_map(fn (array $row): int => $row['lead_id'], $customerRows)),
                            'conversion_event_ids' => array_values(array_map(fn (array $row): int => $row['event_id'], $customerRows)),
                            'cost_entry_ids' => $cost['ids'] ?? [],
                            'cost_references' => $cost['references'] ?? [],
                            'revenue_ids' => array_values(array_unique($revenueIds)),
                            'retention_observation_ids' => array_map(fn (int $customerId): int => $retentionByCustomer[$customerId]['observation_id'], array_intersect($customerIds, array_keys($retentionByCustomer))),
                            'shared_cost_entry_ids_excluded' => $sharedCostIds,
                            'revenue_basis' => 'ProductRevenue.amount_decimal by month, linked to the acquired customer through CustomerInvoice.customer_id.',
                        ],
                    ]);
                }
            }

            return $run->load('results');
        }, 3);
    }
}
