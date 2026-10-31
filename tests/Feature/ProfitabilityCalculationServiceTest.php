<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\PaidCampaign;
use App\Models\Product;
use App\Models\ProductCostAllocationSnapshot;
use App\Models\ProductResource;
use App\Models\ProductResourceAllocation;
use App\Models\ProductRevenue;
use App\Models\Project;
use App\Models\VendorInvoice;
use App\Services\ProductCostService;
use App\Services\ProfitabilityCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitabilityCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->create([
            'productable_type' => 'course',
            'productable_id' => ((int) Product::query()->max('productable_id')) + 1,
        ]);
    }

    private function resource(string $costType, string $amount, string $currency = 'RON'): ProductResource
    {
        $invoice = VendorInvoice::factory()->create(['month' => '2026-10-01', 'currency' => $currency]);

        return ProductResource::withoutEvents(fn () => ProductResource::factory()->create([
            'name' => ucfirst($costType).' source',
            'cost_type' => $costType,
            'amount' => 10000,
            'amount_decimal' => $amount,
            'currency_code' => $currency,
            'month' => '2026-10-01',
            'resourceable_type' => 'vendor_invoice',
            'resourceable_id' => $invoice->getKey(),
        ]));
    }

    private function allocation(ProductResource $resource, Model $target, int $percent, array $attributes = []): ProductResourceAllocation
    {
        $type = match (true) {
            $target instanceof Product => 'product',
            $target instanceof Project => 'project',
            $target instanceof PaidCampaign => 'paid_campaign',
            default => 'unsupported',
        };

        return ProductResourceAllocation::withoutEvents(fn () => ProductResourceAllocation::query()->create(array_merge([
            'product_resource_id' => $resource->getKey(),
            'targetable_type' => $type,
            'targetable_id' => $target->getKey(),
            'allocation_percent' => $percent,
            'month' => '2026-10-01',
            'effective_from' => '2026-01-01',
            'allocation_method' => 'percentage',
            'rule_version' => 1,
        ], $attributes)));
    }

    private function revenue(Product $product, string $amount, string $currency = 'RON'): ProductRevenue
    {
        return ProductRevenue::query()->create([
            'product_id' => $product->getKey(),
            'customer_invoice_id' => CustomerInvoice::factory()->create()->getKey(),
            'month' => '2026-10-01',
            'amount' => 10000,
            'amount_decimal' => $amount,
            'currency_code' => $currency,
        ]);
    }

    public function test_overhead_uses_effective_dated_versioned_rules_and_keeps_partial_remainder_visible(): void
    {
        $product = $this->product();
        $resource = $this->resource('overhead', '30.00');
        $this->allocation($resource, $product, 100, [
            'effective_from' => '2026-10-01',
            'effective_until' => '2026-12-31',
            'rule_version' => 3,
        ]);

        app(ProductCostService::class)->recalculateForResource($resource);

        $snapshot = ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)
            ->firstOrFail();

        $this->assertSame(3, $snapshot->rule_snapshot['rule_version']);
        $this->assertSame('percentage', $snapshot->rule_snapshot['method']);
        $this->assertSame('overhead', $snapshot->rule_snapshot['cost_type']);
        $this->assertSame('2026-10-01', $snapshot->rule_snapshot['effective_from']);

        $invalid = $this->resource('overhead', '12.00');
        $this->allocation($invalid, $product, 80);

        app(ProductCostService::class)->recalculateForResource($invalid);

        $this->assertDatabaseHas('product_cost_allocation_snapshots', [
            'product_resource_id' => $invalid->getKey(),
            'status' => ProductCostAllocationSnapshot::STATUS_ALLOCATED,
            'allocated_amount_decimal' => '9.60',
        ]);
        $this->assertDatabaseHas('product_cost_allocation_snapshots', [
            'product_resource_id' => $invalid->getKey(),
            'status' => ProductCostAllocationSnapshot::STATUS_UNALLOCATED,
            'allocated_amount_decimal' => '2.40',
        ]);
    }

    public function test_effective_dates_exclude_rules_outside_the_source_period(): void
    {
        $product = $this->product();
        $resource = $this->resource('direct', '45.00');
        $this->allocation($resource, $product, 100, ['effective_from' => '2026-11-01']);

        app(ProductCostService::class)->recalculateForResource($resource);

        $this->assertDatabaseHas('product_cost_allocation_snapshots', [
            'product_resource_id' => $resource->getKey(),
            'status' => ProductCostAllocationSnapshot::STATUS_UNALLOCATED,
            'allocated_amount_decimal' => '45.00',
        ]);
    }

    public function test_calculation_keeps_direct_overhead_and_revenue_separate_and_is_explicitly_provisional(): void
    {
        $product = $this->product();
        $this->revenue($product, '100.00');

        $direct = $this->resource('direct', '20.00');
        $overhead = $this->resource('overhead', '30.00');
        $this->allocation($direct, $product, 100);
        $this->allocation($overhead, $product, 100);
        $costService = app(ProductCostService::class);
        $costService->recalculateForResource($direct);
        $costService->recalculateForResource($overhead);

        $run = app(ProfitabilityCalculationService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame('provisional', $run->status);
        $this->assertSame('operational-contribution-v1', $run->formula_version);
        $this->assertSame('provisional_complete', $result->result_status);
        $this->assertSame('100.00', $result->revenue_amount);
        $this->assertSame('20.00', $result->direct_cost_amount);
        $this->assertSame('30.00', $result->overhead_cost_amount);
        $this->assertSame('50.00', $result->provisional_profit_amount);
        $this->assertSame('50.00', $result->provisional_margin_percent);
        $this->assertCount(1, $result->source_snapshot['revenue_ids']);
        $this->assertCount(2, $result->source_snapshot['cost_snapshot_ids']);
    }

    public function test_unallocated_costs_and_missing_revenue_prevent_profit_from_being_presented_as_zero_or_complete(): void
    {
        $product = $this->product();
        $this->revenue($product, '100.00');
        $resource = $this->resource('direct', '40.00');
        $this->allocation($resource, $product, 50);
        app(ProductCostService::class)->recalculateForResource($resource);

        $run = app(ProfitabilityCalculationService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame('incomplete', $result->result_status);
        $this->assertNull($result->provisional_profit_amount);
        $this->assertNull($result->provisional_margin_percent);
        $this->assertSame('20.00', $result->direct_cost_amount);
        $this->assertSame('unallocated_cost', $run->exceptions[0]['type']);
    }

    public function test_currencies_are_separate_and_unknown_canonical_revenue_is_excluded(): void
    {
        $product = $this->product();
        $this->revenue($product, '100.00', 'RON');
        $this->revenue($product, '80.00', 'EUR');
        $unknown = $this->revenue($product, '12.00');
        $unknown->update(['amount_decimal' => null]);

        foreach ([['RON', '20.00'], ['EUR', '10.00']] as [$currency, $amount]) {
            $resource = $this->resource('direct', $amount, $currency);
            $this->allocation($resource, $product, 100);
            app(ProductCostService::class)->recalculateForResource($resource);
        }

        $run = app(ProfitabilityCalculationService::class)->calculate('2026-10-01', '2026-10-31');

        $this->assertSame(['EUR', 'RON'], $run->results->pluck('currency_code')->sort()->values()->all());
        $this->assertSame('70.00', $run->results->firstWhere('currency_code', 'EUR')->provisional_profit_amount);
        $this->assertNull($run->results->firstWhere('currency_code', 'RON')->provisional_profit_amount);
        $this->assertSame('incomplete', $run->results->firstWhere('currency_code', 'RON')->result_status);
        $this->assertContains('unknown_revenue_input', array_column($run->exceptions, 'type'));
    }

    public function test_updating_an_allocation_rule_advances_its_version_and_preserves_prior_snapshots(): void
    {
        $product = $this->product();
        $resource = $this->resource('direct', '40.00');
        $allocation = $this->allocation($resource, $product, 100);
        app(ProductCostService::class)->recalculateForResource($resource);

        $allocation->update(['allocation_percent' => 75]);

        $this->assertSame(2, $allocation->fresh()->rule_version);
        $this->assertSame(1, ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('version', 1)
            ->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)
            ->firstOrFail()->rule_snapshot['rule_version']);
        $this->assertSame(2, ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('version', 2)
            ->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)
            ->firstOrFail()->rule_snapshot['rule_version']);
    }

    public function test_calculation_runs_and_results_are_immutable_and_each_correction_creates_a_new_run(): void
    {
        $product = $this->product();
        $this->revenue($product, '100.00');
        $resource = $this->resource('direct', '25.00');
        $this->allocation($resource, $product, 100);
        app(ProductCostService::class)->recalculateForResource($resource);

        $service = app(ProfitabilityCalculationService::class);
        $first = $service->calculate('2026-10-01', '2026-10-31');
        $second = $service->calculate('2026-10-01', '2026-10-31');

        $this->assertNotSame($first->run_id, $second->run_id);
        $this->assertCount(1, $first->results->first()->source_snapshot['cost_snapshot_ids']);
        $this->expectException(\LogicException::class);
        $first->results->first()->update(['revenue_amount' => '999.00']);
    }
}
