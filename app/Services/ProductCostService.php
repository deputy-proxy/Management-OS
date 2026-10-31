<?php

namespace App\Services;

use App\Models\PaidCampaign;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ProductCostAllocationSnapshot;
use App\Models\ProductResource;
use App\Models\ProductResourceAllocation;
use App\Models\Project;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCostService
{
    public function recalculateForResource(ProductResource $resource): void
    {
        DB::transaction(function () use ($resource): void {
            /** @var ProductResource $lockedResource */
            $lockedResource = ProductResource::withTrashed()->lockForUpdate()->findOrFail($resource->getKey());
            $allocations = ProductResourceAllocation::query()
                ->where('product_resource_id', $lockedResource->getKey())
                ->where(function ($query) use ($lockedResource): void {
                    $query->whereDate('effective_from', '<=', $lockedResource->month)
                        ->orWhere(function ($fallback) use ($lockedResource): void {
                            $fallback->whereNull('effective_from')
                                ->whereDate('month', '<=', $lockedResource->month);
                        });
                })
                ->where(function ($query) use ($lockedResource): void {
                    $query->whereNull('effective_until')
                        ->orWhereDate('effective_until', '>=', $lockedResource->month);
                })
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $percentTotal = 0;
            foreach ($allocations as $allocation) {
                if ($allocation->allocation_method !== 'percentage') {
                    throw ValidationException::withMessages([
                        'allocation_method' => 'The configured allocation method is not supported.',
                    ]);
                }

                if ($allocation->effective_from && $allocation->effective_until
                    && Carbon::parse($allocation->effective_until)->lt(Carbon::parse($allocation->effective_from))) {
                    throw ValidationException::withMessages([
                        'effective_until' => 'The allocation end date must be on or after its start date.',
                    ]);
                }
                $percent = (int) $allocation->allocation_percent;
                if ($percent < 0 || $percent > 100) {
                    throw ValidationException::withMessages([
                        'allocation_percent' => 'Allocation percentages must be between 0 and 100.',
                    ]);
                }

                $percentTotal += $percent;
                if ($percentTotal > 100) {
                    throw ValidationException::withMessages([
                        'allocation_percent' => 'The configured allocation percentages cannot exceed 100%.',
                    ]);
                }
            }

            $sourceSnapshot = $this->sourceSnapshot($lockedResource);
            $version = ((int) ProductCostAllocationSnapshot::query()
                ->where('product_resource_id', $lockedResource->getKey())
                ->max('version')) + 1;
            $runId = (string) Str::uuid();

            if ($lockedResource->trashed()) {
                ProductCost::query()->where('product_resource_id', $lockedResource->getKey())->delete();
                $lockedResource->withoutEvents(fn () => $lockedResource->update(['allocated_percentage' => null]));
                $this->snapshot(
                    $lockedResource,
                    $version,
                    $runId,
                    ProductCostAllocationSnapshot::STATUS_UNKNOWN,
                    null,
                    null,
                    null,
                    null,
                    $sourceSnapshot,
                    ['reason' => 'The source resource has been deleted.'],
                    $lockedResource->currency_code,
                    $lockedResource->amount_decimal,
                );

                return;
            }

            $amount = $lockedResource->amount_decimal;
            $currency = strtoupper((string) $lockedResource->currency_code);
            $sourceIsKnown = $amount !== null
                && $currency !== ''
                && preg_match('/^[A-Z]{3}$/', $currency) === 1;

            if (! $sourceIsKnown) {
                ProductCost::query()->where('product_resource_id', $lockedResource->getKey())->delete();
                $lockedResource->withoutEvents(fn () => $lockedResource->update(['allocated_percentage' => null]));
                $this->snapshot(
                    $lockedResource,
                    $version,
                    $runId,
                    ProductCostAllocationSnapshot::STATUS_UNKNOWN,
                    null,
                    null,
                    null,
                    null,
                    $sourceSnapshot,
                    ['reason' => 'Canonical source amount and currency are required; legacy units are not inferred.'],
                );

                return;
            }

            $sourceAmount = BigDecimal::of((string) $amount)->toScale(2, RoundingMode::HALF_UP);
            $resolved = [];
            $invalidTargets = [];
            foreach ($allocations as $allocation) {
                $targetType = $allocation->targetable_type;
                $targetId = $allocation->targetable_id;
                $target = $this->findTarget($targetType, $targetId);
                if (! $target || $target->trashed()) {
                    $invalidTargets[] = [
                        'allocation_id' => $allocation->getKey(),
                        'target_type' => $targetType,
                        'target_id' => $targetId,
                        'reason' => 'Allocation target is missing or deleted.',
                    ];

                    continue;
                }

                $configuredCurrency = strtoupper((string) $allocation->currency_code);
                if ($configuredCurrency !== '' && $configuredCurrency !== $currency) {
                    throw ValidationException::withMessages([
                        'currency_code' => 'Allocation currency must match the source currency. Currency conversion is not configured.',
                    ]);
                }

                $resolved[] = [
                    'allocation' => $allocation,
                    'type' => $targetType,
                    'id' => (int) $targetId,
                    'percent' => (int) $allocation->allocation_percent,
                    'class' => $target::class,
                ];
            }

            if ($invalidTargets !== []) {
                ProductCost::query()->where('product_resource_id', $lockedResource->getKey())->delete();
                $lockedResource->withoutEvents(fn () => $lockedResource->update(['allocated_percentage' => null]));
                $this->snapshot(
                    $lockedResource,
                    $version,
                    $runId,
                    ProductCostAllocationSnapshot::STATUS_UNKNOWN,
                    null,
                    null,
                    null,
                    null,
                    $sourceSnapshot,
                    ['reason' => 'At least one configured allocation target is unsupported, missing, or deleted.', 'invalid_targets' => $invalidTargets],
                    $currency,
                    $amount,
                );

                return;
            }

            $allocatedPercent = array_sum(array_column($resolved, 'percent'));
            $allocatedSoFar = BigDecimal::zero();

            foreach ($resolved as $index => $entry) {
                $allocatedAmount = $sourceAmount->multipliedBy($entry['percent'])
                    ->dividedBy(100, 2, RoundingMode::HALF_UP);
                $allocatedSoFar = $allocatedSoFar->plus($allocatedAmount);
                $allocatedAmountString = $allocatedAmount->toScale(2, RoundingMode::HALF_UP)->__toString();
                $resolved[$index]['allocated_amount_decimal'] = $allocatedAmountString;

                $this->snapshot(
                    $lockedResource,
                    $version,
                    $runId,
                    ProductCostAllocationSnapshot::STATUS_ALLOCATED,
                    $entry['type'],
                    $entry['id'],
                    $entry['percent'],
                    $allocatedAmountString,
                    $sourceSnapshot,
                    [
                        'allocation_id' => $entry['allocation']->getKey(),
                        'percentage' => $entry['percent'],
                        'currency' => $currency,
                        'method' => $entry['allocation']->allocation_method,
                        'effective_from' => Carbon::parse($entry['allocation']->effective_from ?? $entry['allocation']->month)->toDateString(),
                        'effective_until' => $entry['allocation']->effective_until ? Carbon::parse($entry['allocation']->effective_until)->toDateString() : null,
                        'rule_version' => $entry['allocation']->rule_version,
                        'cost_type' => $lockedResource->cost_type,
                    ],
                    $currency,
                    $amount,
                );
            }

            $unallocatedAmount = $sourceAmount->minus($allocatedSoFar);
            if (! $unallocatedAmount->isZero() || $resolved === []) {
                $this->snapshot(
                    $lockedResource,
                    $version,
                    $runId,
                    ProductCostAllocationSnapshot::STATUS_UNALLOCATED,
                    null,
                    null,
                    100 - $allocatedPercent,
                    $unallocatedAmount->toScale(2, RoundingMode::HALF_UP)->__toString(),
                    $sourceSnapshot,
                    [
                        'reason' => $resolved === []
                            ? 'No allocation rule is configured.'
                            : ($allocatedPercent < 100
                                ? 'The configured rules leave a portion unallocated.'
                                : 'Rounding residual remains visible; it is not redistributed.'),
                        'rules' => array_map(fn (array $entry): array => [
                            'allocation_id' => $entry['allocation']->getKey(),
                            'rule_version' => $entry['allocation']->rule_version,
                            'method' => $entry['allocation']->allocation_method,
                            'percentage' => $entry['percent'],
                        ], $resolved),
                    ],
                    $currency,
                    $amount,
                );
            }

            // Keep the legacy product-cost projection compatible for existing readers.
            $productEntries = array_values(array_filter(
                $resolved,
                fn (array $entry): bool => $entry['class'] === Product::class,
            ));
            ProductCost::query()->where('product_resource_id', $lockedResource->getKey())->delete();
            foreach ($productEntries as $entry) {
                $legacyAmount = (int) round((float) $lockedResource->amount * $entry['percent'] / 100);
                ProductCost::query()->create([
                    'product_id' => $entry['id'],
                    'product_resource_id' => $lockedResource->getKey(),
                    'month' => $lockedResource->month,
                    'allocation_percent' => $entry['percent'],
                    'resource_amount' => $lockedResource->amount,
                    'allocated_amount' => $legacyAmount,
                    'resource_amount_decimal' => $amount,
                    'allocated_amount_decimal' => $entry['allocated_amount_decimal'],
                    'currency_code' => $currency,
                ]);
            }

            $lockedResource->withoutEvents(function () use ($lockedResource, $allocatedPercent): void {
                $lockedResource->update(['allocated_percentage' => $allocatedPercent]);
            });
        }, 3);
    }

    private function findTarget(?string $type, ?int $id): Product|Project|PaidCampaign|null
    {
        if ($type === null || $id === null) {
            return null;
        }

        return match ($type) {
            'product' => Product::withTrashed()->find($id),
            'project' => Project::withTrashed()->find($id),
            'paid_campaign' => PaidCampaign::withTrashed()->find($id),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(ProductResource $resource): array
    {
        return [
            'resource_id' => $resource->getKey(),
            'name' => $resource->name,
            'cost_type' => $resource->cost_type,
            'month' => $resource->month?->toDateString(),
            'source_type' => $resource->resourceable_type,
            'source_id' => $resource->resourceable_id,
            'source_name' => $resource->resourceable?->getAttribute('name')
                ?? $resource->resourceable?->getAttribute('title')
                ?? $resource->resourceable?->getAttribute('invoice_number')
                ?? $resource->resourceable?->getAttribute('identifier'),
            'source_reference' => $resource->resourceable?->getAttribute('invoice_number')
                ?? $resource->resourceable?->getAttribute('identifier'),
            'amount_decimal' => $resource->amount_decimal,
            'currency_code' => $resource->currency_code,
        ];
    }

    /** @param array<string, mixed> $sourceSnapshot
     * @param  array<string, mixed>  $ruleSnapshot
     */
    private function snapshot(
        ProductResource $resource,
        int $version,
        string $runId,
        string $status,
        ?string $targetType,
        ?int $targetId,
        ?int $percent,
        string|int|float|null $allocatedAmount,
        array $sourceSnapshot,
        array $ruleSnapshot,
        ?string $currency = null,
        string|int|float|null $sourceAmount = null,
    ): void {
        ProductCostAllocationSnapshot::query()->create([
            'product_resource_id' => $resource->getKey(),
            'run_id' => $runId,
            'version' => $version,
            'status' => $status,
            'targetable_type' => $targetType,
            'targetable_id' => $targetId,
            'allocation_percent' => $percent,
            'source_amount_decimal' => $sourceAmount,
            'allocated_amount_decimal' => $allocatedAmount,
            'currency_code' => $currency,
            'source_type' => $resource->resourceable_type,
            'source_id' => $resource->resourceable_id,
            'source_snapshot' => $sourceSnapshot,
            'rule_snapshot' => $ruleSnapshot,
        ]);
    }
}
