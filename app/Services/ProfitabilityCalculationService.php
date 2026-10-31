<?php

namespace App\Services;

use App\Models\ProductCostAllocationSnapshot;
use App\Models\ProductResource;
use App\Models\ProductRevenue;
use App\Models\ProfitabilityCalculationResult;
use App\Models\ProfitabilityCalculationRun;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfitabilityCalculationService
{
    public function calculate(string $periodStart, string $periodEnd): ProfitabilityCalculationRun
    {
        $from = Carbon::createFromFormat('!Y-m-d', $periodStart, 'Europe/Bucharest');
        $to = Carbon::createFromFormat('!Y-m-d', $periodEnd, 'Europe/Bucharest');

        if (! $from || ! $to || $from->gt($to)) {
            throw ValidationException::withMessages([
                'period_end' => 'The reporting end date must be on or after the start date.',
            ]);
        }

        return DB::transaction(function () use ($from, $to): ProfitabilityCalculationRun {
            $exceptions = [];
            $buckets = [];
            $unallocatedByCurrency = [];
            $unknownRevenueByProduct = [];
            $unknownRevenueByBucket = [];
            $unknownCostCurrency = false;

            $revenues = ProductRevenue::query()
                ->with('product')
                ->whereBetween('month', [$from->toDateString(), $to->toDateString()])
                ->orderBy('id')
                ->get();

            foreach ($revenues as $revenue) {
                $currency = strtoupper((string) $revenue->currency_code);
                if ($revenue->amount_decimal === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                    if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
                        $unknownRevenueByBucket[$this->key((int) $revenue->product_id, $currency)] = true;
                    } else {
                        $unknownRevenueByProduct[(int) $revenue->product_id] = true;
                    }
                    $exceptions[] = [
                        'type' => 'unknown_revenue_input',
                        'source_type' => 'product_revenue',
                        'source_id' => $revenue->getKey(),
                        'product_id' => $revenue->product_id,
                        'currency' => preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null,
                        'reason' => 'Canonical revenue amount and a valid currency are required; legacy units are not inferred.',
                    ];

                    continue;
                }

                $key = $this->key((int) $revenue->product_id, $currency);
                $buckets[$key] ??= $this->emptyBucket((int) $revenue->product_id, $currency);
                $buckets[$key]['revenue'] = $buckets[$key]['revenue']->plus(BigDecimal::of((string) $revenue->amount_decimal));
                $buckets[$key]['revenue_ids'][] = $revenue->getKey();
            }

            $latestVersions = ProductCostAllocationSnapshot::query()
                ->select('product_resource_id')
                ->selectRaw('MAX(version) AS latest_version')
                ->groupBy('product_resource_id');

            $snapshots = ProductCostAllocationSnapshot::query()
                ->joinSub($latestVersions, 'latest_runs', function ($join): void {
                    $join->on('product_cost_allocation_snapshots.product_resource_id', '=', 'latest_runs.product_resource_id')
                        ->on('product_cost_allocation_snapshots.version', '=', 'latest_runs.latest_version');
                })
                ->orderBy('product_cost_allocation_snapshots.id')
                ->get(['product_cost_allocation_snapshots.*']);

            foreach ($snapshots as $snapshot) {
                $resource = ProductResource::withTrashed()->find($snapshot->product_resource_id);
                if (! $resource || $resource->trashed()) {
                    $currency = strtoupper((string) $snapshot->currency_code);
                    $exceptions[] = [
                        'type' => 'unknown_cost_source',
                        'snapshot_id' => $snapshot->getKey(),
                        'resource_id' => $snapshot->product_resource_id,
                        'currency' => preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null,
                        'reason' => 'The source resource is missing or deleted.',
                    ];
                    if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
                        $unallocatedByCurrency[$currency] = true;
                    } else {
                        $unknownCostCurrency = true;
                    }

                    continue;
                }

                if (! $resource->month || $resource->month->lt($from) || $resource->month->gt($to)) {
                    continue;
                }

                $currency = strtoupper((string) $snapshot->currency_code);
                if ($snapshot->status === ProductCostAllocationSnapshot::STATUS_UNKNOWN) {
                    $exceptions[] = [
                        'type' => 'unknown_cost_allocation',
                        'snapshot_id' => $snapshot->getKey(),
                        'resource_id' => $resource->getKey(),
                        'currency' => $currency ?: null,
                    ];
                    if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
                        $unallocatedByCurrency[$currency] = true;
                    } else {
                        $unknownCostCurrency = true;
                    }

                    continue;
                }

                if ($snapshot->status === ProductCostAllocationSnapshot::STATUS_UNALLOCATED) {
                    $exceptions[] = [
                        'type' => 'unallocated_cost',
                        'snapshot_id' => $snapshot->getKey(),
                        'resource_id' => $resource->getKey(),
                        'amount' => $snapshot->allocated_amount_decimal,
                        'currency' => $currency ?: null,
                    ];
                    if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
                        $unallocatedByCurrency[$currency] = true;
                    } else {
                        $unknownCostCurrency = true;
                    }

                    continue;
                }

                if ($snapshot->status !== ProductCostAllocationSnapshot::STATUS_ALLOCATED
                    || $snapshot->targetable_type !== 'product'
                    || $snapshot->allocated_amount_decimal === null
                    || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                    $exceptions[] = [
                        'type' => 'excluded_cost_allocation',
                        'snapshot_id' => $snapshot->getKey(),
                        'resource_id' => $resource->getKey(),
                        'target_type' => $snapshot->targetable_type,
                        'reason' => 'Only canonical product allocations can enter the provisional product calculation.',
                    ];
                    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                        $unknownCostCurrency = true;
                    }

                    continue;
                }

                if (! in_array($resource->cost_type, ['direct', 'overhead'], true)) {
                    $exceptions[] = [
                        'type' => 'unclassified_cost_source',
                        'snapshot_id' => $snapshot->getKey(),
                        'resource_id' => $resource->getKey(),
                        'currency' => $currency,
                    ];
                    $unallocatedByCurrency[$currency] = true;

                    continue;
                }

                $productId = (int) $snapshot->targetable_id;
                $key = $this->key($productId, $currency);
                $buckets[$key] ??= $this->emptyBucket($productId, $currency);
                $amount = BigDecimal::of((string) $snapshot->allocated_amount_decimal);
                $costField = $resource->cost_type === 'overhead' ? 'overhead' : 'direct';
                $buckets[$key][$costField] = $buckets[$key][$costField]->plus($amount);
                $buckets[$key]['cost_snapshot_ids'][] = $snapshot->getKey();
                $buckets[$key]['cost_resource_ids'][] = $resource->getKey();
            }

            $run = ProfitabilityCalculationRun::query()->create([
                'run_id' => (string) Str::uuid(),
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'status' => 'provisional',
                'formula_version' => 'operational-contribution-v1',
                'assumptions' => [
                    'label' => 'Provisional operational contribution; not approved profitability or statutory accounting profit.',
                    'period_basis' => 'Product revenue and source resource month fields, as stored.',
                    'revenue_basis' => 'ProductRevenue canonical amount_decimal and currency_code only; no invoice inference or recognition assumption.',
                    'cost_basis' => 'Latest versioned ProductCostAllocationSnapshot records for each source resource, classified explicitly as direct or overhead.',
                    'formula' => 'provisional_profit = canonical_product_revenue - allocated_direct_cost - allocated_overhead_cost; margin = provisional_profit / revenue * 100.',
                    'currency' => 'Currencies remain separate; no FX conversion.',
                    'completeness' => 'Profit and margin are withheld when revenue or cost coverage is incomplete or unallocated/unknown costs affect the currency period.',
                ],
                'exceptions' => $exceptions,
            ]);

            foreach ($buckets as $bucket) {
                $currencyComplete = ! isset($unallocatedByCurrency[$bucket['currency']]) && ! $unknownCostCurrency;
                $complete = $bucket['revenue_ids'] !== []
                    && $bucket['cost_resource_ids'] !== []
                    && $currencyComplete
                    && ! isset($unknownRevenueByProduct[$bucket['product_id']])
                    && ! isset($unknownRevenueByBucket[$this->key($bucket['product_id'], $bucket['currency'])]);
                $profit = null;
                $margin = null;

                if ($complete) {
                    $profit = $bucket['revenue']->minus($bucket['direct'])->minus($bucket['overhead']);
                    if (! $bucket['revenue']->isZero()) {
                        $margin = $profit->multipliedBy(100)->dividedBy($bucket['revenue'], 2, RoundingMode::HALF_UP);
                    }
                }

                ProfitabilityCalculationResult::query()->create([
                    'profitability_calculation_run_id' => $run->getKey(),
                    'product_id' => $bucket['product_id'],
                    'currency_code' => $bucket['currency'],
                    'result_status' => $complete ? 'provisional_complete' : 'incomplete',
                    'revenue_amount' => $bucket['revenue_ids'] !== [] ? $bucket['revenue']->toScale(2, RoundingMode::HALF_UP)->__toString() : null,
                    'direct_cost_amount' => $bucket['cost_resource_ids'] !== [] ? $bucket['direct']->toScale(2, RoundingMode::HALF_UP)->__toString() : null,
                    'overhead_cost_amount' => $bucket['cost_resource_ids'] !== [] ? $bucket['overhead']->toScale(2, RoundingMode::HALF_UP)->__toString() : null,
                    'provisional_profit_amount' => $profit?->toScale(2, RoundingMode::HALF_UP)->__toString(),
                    'provisional_margin_percent' => $margin?->__toString(),
                    'source_snapshot' => [
                        'revenue_ids' => $bucket['revenue_ids'],
                        'cost_snapshot_ids' => $bucket['cost_snapshot_ids'],
                        'cost_resource_ids' => array_values(array_unique($bucket['cost_resource_ids'])),
                        'currency_complete' => $currencyComplete,
                    ],
                ]);
            }

            return $run->load('results.product');
        }, 3);
    }

    private function key(int $productId, string $currency): string
    {
        return $productId.'|'.$currency;
    }

    /** @return array<string, mixed> */
    private function emptyBucket(int $productId, string $currency): array
    {
        return [
            'product_id' => $productId,
            'currency' => $currency,
            'revenue' => BigDecimal::zero(),
            'direct' => BigDecimal::zero(),
            'overhead' => BigDecimal::zero(),
            'revenue_ids' => [],
            'cost_snapshot_ids' => [],
            'cost_resource_ids' => [],
        ];
    }
}
