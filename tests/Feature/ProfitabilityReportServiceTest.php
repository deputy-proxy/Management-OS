<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Product;
use App\Models\ProductResource;
use App\Models\ProductResourceAllocation;
use App\Models\ProductRevenue;
use App\Models\ProfitabilityCalculationRun;
use App\Models\VendorInvoice;
use App\Services\ProductCostService;
use App\Services\ProfitabilityCalculationService;
use App\Services\ProfitabilityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfitabilityReportServiceTest extends TestCase
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
        $invoice = VendorInvoice::factory()->create([
            'month' => '2026-10-01',
            'currency' => $currency,
        ]);

        return ProductResource::withoutEvents(fn (): ProductResource => ProductResource::factory()->create([
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

    private function allocation(ProductResource $resource, Product $product): ProductResourceAllocation
    {
        return ProductResourceAllocation::withoutEvents(fn (): ProductResourceAllocation => ProductResourceAllocation::query()->create([
            'product_resource_id' => $resource->getKey(),
            'targetable_type' => 'product',
            'targetable_id' => $product->getKey(),
            'allocation_percent' => 100,
            'month' => '2026-10-01',
            'effective_from' => '2026-01-01',
            'allocation_method' => 'percentage',
            'rule_version' => 1,
        ]));
    }

    private function revenue(
        Product $product,
        string $amount,
        string $currency = 'RON',
        ?Customer $customer = null,
        bool $unattributed = false,
    ): ProductRevenue {
        $customer ??= Customer::factory()->create();
        $invoice = CustomerInvoice::factory()->create(['customer_id' => $customer->getKey()]);

        if ($unattributed) {
            DB::table('customer_invoices')
                ->where('id', $invoice->getKey())
                ->update(['customer_id' => 999999999]);
        }

        return ProductRevenue::query()->create([
            'product_id' => $product->getKey(),
            'customer_invoice_id' => $invoice->getKey(),
            'month' => '2026-10-01',
            'amount' => 10000,
            'amount_decimal' => $amount,
            'currency_code' => $currency,
        ]);
    }

    private function calculate(Product $product): ProfitabilityCalculationRun
    {
        foreach ([
            ['direct', '20.00', 'RON'],
            ['overhead', '10.00', 'RON'],
            ['direct', '10.00', 'EUR'],
            ['overhead', '5.00', 'EUR'],
        ] as [$type, $amount, $currency]) {
            $resource = $this->resource($type, $amount, $currency);
            $this->allocation($resource, $product);
            app(ProductCostService::class)->recalculateForResource($resource);
        }

        return app(ProfitabilityCalculationService::class)->calculate('2026-10-01', '2026-10-31');
    }

    public function test_period_totals_reconcile_to_calculation_results_and_never_combine_currencies(): void
    {
        $product = $this->product();
        $this->revenue($product, '100.00', 'RON');
        $this->revenue($product, '40.00', 'EUR');

        $secondProduct = $this->product();
        $this->revenue($secondProduct, '25.00', 'RON');
        foreach ([['direct', '5.00'], ['overhead', '2.00']] as [$type, $amount]) {
            $resource = $this->resource($type, $amount, 'RON');
            $this->allocation($resource, $secondProduct);
            app(ProductCostService::class)->recalculateForResource($resource);
        }

        $run = $this->calculate($product);

        $report = app(ProfitabilityReportService::class)->report($run);

        $this->assertSame(['EUR', 'RON'], array_keys($report['currency_totals']));
        $this->assertSame('40.00', $report['currency_totals']['EUR']['revenue_amount']);
        $this->assertSame('10.00', $report['currency_totals']['EUR']['direct_cost_amount']);
        $this->assertSame('5.00', $report['currency_totals']['EUR']['overhead_cost_amount']);
        $this->assertSame('25.00', $report['currency_totals']['EUR']['provisional_profit_amount']);
        $this->assertSame('125.00', $report['currency_totals']['RON']['revenue_amount']);
        $this->assertSame('25.00', $report['currency_totals']['RON']['direct_cost_amount']);
        $this->assertSame('12.00', $report['currency_totals']['RON']['overhead_cost_amount']);
        $this->assertSame('88.00', $report['currency_totals']['RON']['provisional_profit_amount']);
        $this->assertSame(3, $report['results']->count());

        $filtered = app(ProfitabilityReportService::class)->report($run, ['currency_code' => 'RON']);
        $this->assertSame(['RON'], array_keys($filtered['currency_totals']));
        $this->assertCount(2, $filtered['results']);

        $productFiltered = app(ProfitabilityReportService::class)->report($run, ['product_id' => $secondProduct->getKey()]);
        $this->assertCount(1, $productFiltered['results']);
        $this->assertSame((int) $secondProduct->getKey(), (int) $productFiltered['results']->first()->product_id);
        $this->assertSame('25.00', $productFiltered['currency_totals']['RON']['revenue_amount']);
        $this->assertSame('18.00', $productFiltered['currency_totals']['RON']['provisional_profit_amount']);
    }

    public function test_customer_drilldown_reconciles_only_explicit_revenue_relationships_and_never_allocates_product_costs_to_customers(): void
    {
        $product = $this->product();
        $firstCustomer = Customer::factory()->create(['name' => 'First customer']);
        $secondCustomer = Customer::factory()->create(['name' => 'Second customer']);

        $this->revenue($product, '60.00', 'RON', $firstCustomer);
        $this->revenue($product, '30.00', 'RON', $secondCustomer);
        $this->revenue($product, '10.00', 'RON', null, true);
        $run = $this->calculate($product);

        $report = app(ProfitabilityReportService::class)->report($run, [], true);

        $this->assertCount(2, $report['customers']);
        $this->assertSame('60.00', collect($report['customers'])->firstWhere('customer_id', $firstCustomer->id)['revenue_amount']);
        $this->assertSame('30.00', collect($report['customers'])->firstWhere('customer_id', $secondCustomer->id)['revenue_amount']);
        $this->assertSame('10.00', $report['unattributed'][0]['revenue_amount']);
        $this->assertSame('RON', $report['unattributed'][0]['currency']);
        $this->assertNull($report['customers'][0]['direct_cost_amount']);
        $this->assertNull($report['customers'][0]['overhead_cost_amount']);
        $this->assertNull($report['customers'][0]['provisional_profit_amount']);
        $this->assertNull($report['customers'][0]['provisional_margin_percent']);

        $customerRevenue = collect($report['customers'])->sum(fn (array $row): float => (float) $row['revenue_amount']);
        $this->assertSame(90.0, $customerRevenue);
        $this->assertSame(100.0, $customerRevenue + (float) $report['unattributed'][0]['revenue_amount']);

        $withoutPermission = app(ProfitabilityReportService::class)->report($run);
        $this->assertFalse($withoutPermission['customer_details_available']);
        $this->assertSame([], $withoutPermission['customers']);
    }

    public function test_customer_amounts_are_withheld_when_current_source_rows_no_longer_reconcile_to_the_immutable_run(): void
    {
        $product = $this->product();
        $customer = Customer::factory()->create();
        $revenue = $this->revenue($product, '100.00', 'RON', $customer);
        $run = $this->calculate($product);

        $revenue->update(['amount_decimal' => '101.00']);

        $report = app(ProfitabilityReportService::class)->report($run, [], true);

        $this->assertSame([], $report['customers']);
        $this->assertCount(1, $report['customer_reconciliation_issues']);
        $this->assertStringContainsString('no longer reconcile', $report['customer_reconciliation_issues'][0]['reason']);
    }

    public function test_project_dimension_is_explicitly_unavailable_instead_of_inferred(): void
    {
        $run = ProfitabilityCalculationRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => 'provisional',
            'formula_version' => 'operational-contribution-v1',
            'assumptions' => [],
            'exceptions' => [],
        ]);

        $report = app(ProfitabilityReportService::class)->report($run);

        $this->assertFalse($report['project_dimension']['available']);
        $this->assertStringContainsString('no explicit project attribution', $report['project_dimension']['reason']);
    }
}
