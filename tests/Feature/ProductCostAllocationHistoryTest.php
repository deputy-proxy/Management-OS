<?php

namespace Tests\Feature;

use App\Models\PaidCampaign;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ProductCostAllocationSnapshot;
use App\Models\ProductResource;
use App\Models\ProductResourceAllocation;
use App\Models\Project;
use App\Models\VendorInvoice;
use App\Services\ProductCostService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCostAllocationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function resource(array $attributes = []): ProductResource
    {
        $invoice = VendorInvoice::factory()->create(['month' => '2026-10-01', 'currency' => 'RON']);

        return ProductResource::withoutEvents(fn () => ProductResource::factory()->create(array_merge([
            'name' => 'Source cost',
            'amount' => 10000,
            'amount_decimal' => '100.00',
            'currency_code' => 'RON',
            'month' => '2026-10-01',
            'resourceable_type' => 'vendor_invoice',
            'resourceable_id' => $invoice->getKey(),
        ], $attributes)));
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'productable_type' => 'course',
            'productable_id' => ((int) Product::query()->max('productable_id')) + 1,
        ]);
    }

    private function allocation(ProductResource $resource, Model $target, int $percent, ?string $currency = null): ProductResourceAllocation
    {
        $type = match (true) {
            $target instanceof Product => 'product',
            $target instanceof Project => 'project',
            $target instanceof PaidCampaign => 'paid_campaign',
            default => 'unsupported',
        };

        return ProductResourceAllocation::withoutEvents(fn () => ProductResourceAllocation::query()->create([
            'product_resource_id' => $resource->getKey(),
            'targetable_type' => $type,
            'targetable_id' => $target->getKey(),
            'allocation_percent' => $percent,
            'currency_code' => $currency,
            'month' => '2026-10-01',
        ]));
    }

    public function test_recalculation_keeps_versions_and_allocates_across_supported_target_types(): void
    {
        $resource = $this->resource();
        $product = $this->product();
        $project = Project::factory()->create();
        $campaign = PaidCampaign::factory()->create();

        $this->allocation($resource, $product, 30, 'RON');
        $this->allocation($resource, $project, 30);
        $this->allocation($resource, $campaign, 30);

        $service = app(ProductCostService::class);
        $service->recalculateForResource($resource);

        $firstRun = ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('version', 1)
            ->get();

        $this->assertCount(4, $firstRun);
        $this->assertSame(3, $firstRun->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)->count());
        $this->assertSame('10.00', $firstRun->where('status', ProductCostAllocationSnapshot::STATUS_UNALLOCATED)->first()->allocated_amount_decimal);
        $this->assertSame(90, $resource->fresh()->allocated_percentage);
        $this->assertSame(1, $firstRun->where('targetable_type', 'project')->count());
        $this->assertSame(1, $firstRun->where('targetable_type', 'paid_campaign')->count());
        $this->assertSame('vendor_invoice', $firstRun->first()->source_type);
        $this->assertSame($resource->resourceable_id, $firstRun->first()->source_snapshot['source_id']);
        $this->assertSame($resource->resourceable_id, $firstRun->first()->source->getKey());
        $this->assertSame($product->getKey(), $firstRun->firstWhere('targetable_type', 'product')->targetable->getKey());
        $this->assertDatabaseHas('product_costs', [
            'product_id' => $product->getKey(),
            'product_resource_id' => $resource->getKey(),
            'allocated_amount_decimal' => '30.00',
            'currency_code' => 'RON',
        ]);

        ProductResource::withoutEvents(fn () => $resource->update(['amount_decimal' => '120.00']));
        $service->recalculateForResource($resource->fresh());

        $this->assertSame(2, ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->distinct()
            ->count('version'));
        $this->assertSame('100.00', ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('version', 1)
            ->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)
            ->first()->source_amount_decimal);
        $this->assertSame('120.00', ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('version', 2)
            ->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)
            ->first()->source_amount_decimal);
    }

    public function test_negative_source_adjustments_keep_their_sign_and_reconcile(): void
    {
        $resource = $this->resource(['amount_decimal' => '-12.50']);
        $first = $this->product();
        $second = $this->product();
        $this->allocation($resource, $first, 50);
        $this->allocation($resource, $second, 50);

        app(ProductCostService::class)->recalculateForResource($resource);

        $amounts = ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)
            ->orderBy('id')
            ->pluck('allocated_amount_decimal')
            ->all();

        $this->assertSame(['-6.25', '-6.25'], $amounts);
        $this->assertSame(0, ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('status', ProductCostAllocationSnapshot::STATUS_UNALLOCATED)
            ->count());
    }

    public function test_rounding_residual_is_deterministic_and_unallocated_amount_remains_visible(): void
    {
        $resource = $this->resource(['amount_decimal' => '0.05']);
        $first = $this->product();
        $second = $this->product();
        $this->allocation($resource, $first, 50);
        $this->allocation($resource, $second, 50);

        app(ProductCostService::class)->recalculateForResource($resource);

        $entries = ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)
            ->orderBy('id')
            ->pluck('allocated_amount_decimal')
            ->all();

        $this->assertSame(['0.03', '0.02'], $entries);
        $this->assertSame(0, ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('status', ProductCostAllocationSnapshot::STATUS_UNALLOCATED)
            ->count());
    }

    public function test_missing_canonical_source_values_are_marked_unknown_not_zero(): void
    {
        $resource = $this->resource(['amount_decimal' => null, 'currency_code' => null]);
        $product = $this->product();
        $this->allocation($resource, $product, 100);

        app(ProductCostService::class)->recalculateForResource($resource);

        $snapshot = ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->first();

        $this->assertSame(ProductCostAllocationSnapshot::STATUS_UNKNOWN, $snapshot->status);
        $this->assertNull($snapshot->allocated_amount_decimal);
        $this->assertNull($snapshot->currency_code);
    }

    public function test_deleted_source_resource_is_preserved_as_unknown_history(): void
    {
        $resource = $this->resource();
        $product = $this->product();
        $this->allocation($resource, $product, 100);
        app(ProductCostService::class)->recalculateForResource($resource);

        $resource->delete();

        $snapshot = ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->latest('id')
            ->first();

        $this->assertSame(ProductCostAllocationSnapshot::STATUS_UNKNOWN, $snapshot->status);
        $this->assertSame('The source resource has been deleted.', $snapshot->rule_snapshot['reason']);
        $this->assertSame(0, ProductCost::query()
            ->where('product_resource_id', $resource->getKey())
            ->count());
        $this->assertSame(1, ProductCost::withTrashed()
            ->where('product_resource_id', $resource->getKey())
            ->count());
    }

    public function test_deleted_targets_are_recorded_as_unknown_and_not_allocated(): void
    {
        $resource = $this->resource();
        $product = $this->product();
        $this->allocation($resource, $product, 100);
        $product->delete();

        app(ProductCostService::class)->recalculateForResource($resource);

        $this->assertDatabaseHas('product_cost_allocation_snapshots', [
            'product_resource_id' => $resource->getKey(),
            'status' => ProductCostAllocationSnapshot::STATUS_UNKNOWN,
        ]);
        $this->assertSame(0, ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->where('status', ProductCostAllocationSnapshot::STATUS_ALLOCATED)
            ->count());
    }

    public function test_legacy_product_id_writes_stay_synchronized_with_the_polymorphic_target(): void
    {
        $resource = $this->resource();
        $first = $this->product();
        $second = $this->product();

        $allocation = ProductResourceAllocation::query()->create([
            'product_resource_id' => $resource->getKey(),
            'product_id' => $first->getKey(),
            'allocation_percent' => 100,
            'month' => '2026-10-01',
        ]);

        $allocation->update(['product_id' => $second->getKey()]);

        $this->assertSame('product', $allocation->fresh()->targetable_type);
        $this->assertSame($second->getKey(), $allocation->fresh()->targetable_id);
        $this->assertSame($second->getKey(), $allocation->fresh()->product_id);
    }

    public function test_resource_without_configured_rules_is_explicitly_unallocated(): void
    {
        $resource = $this->resource();

        app(ProductCostService::class)->recalculateForResource($resource);

        $snapshot = ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->first();

        $this->assertSame(ProductCostAllocationSnapshot::STATUS_UNALLOCATED, $snapshot->status);
        $this->assertSame('100.00', $snapshot->allocated_amount_decimal);
        $this->assertSame(100, $snapshot->allocation_percent);
    }

    public function test_currency_mismatch_and_invalid_percentages_roll_back_the_run(): void
    {
        $resource = $this->resource();
        $product = $this->product();
        $allocation = $this->allocation($resource, $product, 50, 'EUR');

        try {
            app(ProductCostService::class)->recalculateForResource($resource);
            $this->fail('Expected a currency mismatch to be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('product_cost_allocation_snapshots', 0);
        }

        ProductResourceAllocation::withoutEvents(fn () => $allocation->update(['currency_code' => 'RON', 'allocation_percent' => 101]));

        try {
            app(ProductCostService::class)->recalculateForResource($resource);
            $this->fail('Expected an invalid percentage to be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('product_cost_allocation_snapshots', 0);
        }
    }

    public function test_mysql_recalculation_uses_row_locks_for_concurrent_versioning(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Row-lock SQL is verified by the dedicated MySQL CI job.');
        }

        $resource = $this->resource();
        $product = $this->product();
        $this->allocation($resource, $product, 100);
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        app(ProductCostService::class)->recalculateForResource($resource);

        $this->assertTrue(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'for update')));
    }

    public function test_repeated_recalculations_are_serialized_into_distinct_auditable_runs(): void
    {
        $resource = $this->resource();
        $product = $this->product();
        $this->allocation($resource, $product, 100);

        $service = app(ProductCostService::class);
        $service->recalculateForResource($resource);
        $service->recalculateForResource($resource);

        $runs = ProductCostAllocationSnapshot::query()
            ->where('product_resource_id', $resource->getKey())
            ->select('run_id', 'version')
            ->distinct()
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $runs);
        $this->assertNotSame($runs[0]->run_id, $runs[1]->run_id);
        $this->assertSame([1, 2], $runs->pluck('version')->all());
    }
}
