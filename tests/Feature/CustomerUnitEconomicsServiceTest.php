<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAcquisitionCostEntry;
use App\Models\CustomerInvoice;
use App\Models\CustomerLead;
use App\Models\CustomerLeadAttributionEvent;
use App\Models\CustomerRetentionObservation;
use App\Models\Product;
use App\Models\ProductRevenue;
use App\Services\CustomerUnitEconomicsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class CustomerUnitEconomicsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer-unit-economics.attribution_model' => 'first_touch_lead_snapshot',
            'customer-unit-economics.deduplication' => 'earliest_verified_conversion',
            'customer-unit-economics.cac_basis' => 'source_category_matched_entries',
            'customer-unit-economics.revenue_basis' => 'canonical_product_revenue',
            'customer-unit-economics.ltv_window' => 'monthly_rows_from_conversion_date',
            'customer-unit-economics.retention_basis' => 'latest_explicit_observation',
            'customer-unit-economics.retention_policy_version' => 'test-retention-v1',
        ]);
    }

    private function convertedLead(Customer $customer, string $category, string $date, ?int $customerIdInEvent = null, bool $recordRetention = true): CustomerLead
    {
        $lead = CustomerLead::factory()->create(['source_category' => $category]);
        DB::table('customer_leads')->where('id', $lead->getKey())->update([
            'status' => 'converted',
            'converted_customer_id' => $customer->getKey(),
            'converted_at' => $date.' 10:00:00',
        ]);

        CustomerLeadAttributionEvent::query()->create([
            'customer_lead_id' => $lead->getKey(),
            'event_type' => 'conversion',
            'idempotency_key' => 'test:conversion:'.$lead->getKey(),
            'snapshot' => [
                'first_touch' => ['source_category' => $category, 'attribution_policy_version' => 'v1'],
                'conversion' => [
                    'customer_id' => $customerIdInEvent ?? $customer->getKey(),
                    'converted_at' => $date.'T10:00:00+02:00',
                ],
            ],
            'occurred_at' => $date.' 10:00:00',
        ]);

        if ($recordRetention && ! CustomerRetentionObservation::query()->where('customer_id', $customer->getKey())->exists()) {
            app(CustomerUnitEconomicsService::class)->recordRetentionObservation([
                'customer_id' => (int) $customer->getKey(),
                'observed_on' => '2026-10-31',
                'state' => 'retained',
                'source_reference' => 'retention-source:'.$customer->getKey(),
                'evidence_reference' => 'retention-evidence:'.$customer->getKey(),
            ]);
        }

        return $lead;
    }

    private function revenue(Customer $customer, string $month, string $amount, string $currency = 'RON'): ProductRevenue
    {
        $invoice = CustomerInvoice::factory()->create(['customer_id' => $customer->getKey()]);
        $product = Product::factory()->create([
            'productable_type' => 'course',
            'productable_id' => ((int) Product::query()->max('productable_id')) + 1,
        ]);

        return ProductRevenue::query()->create([
            'product_id' => $product->getKey(),
            'customer_invoice_id' => $invoice->getKey(),
            'month' => $month,
            'amount' => 10000,
            'amount_decimal' => $amount,
            'currency_code' => $currency,
        ]);
    }

    private function cost(string $category, string $amount, string $currency = 'RON', string $date = '2026-10-01'): CustomerAcquisitionCostEntry
    {
        return app(CustomerUnitEconomicsService::class)->recordAcquisitionCost([
            'incurred_on' => $date,
            'source_category' => $category,
            'amount_decimal' => $amount,
            'currency_code' => $currency,
            'source_reference' => 'vendor-invoice:'.uniqid(),
            'evidence_reference' => 'evidence:'.uniqid(),
        ]);
    }

    public function test_first_touch_deduplicates_customers_and_calculates_signed_lifetime_revenue_by_currency(): void
    {
        $customer = Customer::factory()->create();
        $firstLead = $this->convertedLead($customer, 'paid_search', '2026-10-05');
        $this->convertedLead($customer, 'social', '2026-10-06');

        $this->cost('paid_search', '100.00');
        app(CustomerUnitEconomicsService::class)->recordAcquisitionCost([
            'incurred_on' => '2026-10-02',
            'source_category' => null,
            'amount_decimal' => '30.00',
            'currency_code' => 'RON',
            'source_reference' => 'shared-invoice:1',
            'evidence_reference' => 'evidence:shared-1',
        ]);

        $this->revenue($customer, '2026-10-10', '80.00', 'RON');
        $this->revenue($customer, '2026-10-15', '-10.00', 'RON');
        $this->revenue($customer, '2026-10-20', '50.00', 'USD');

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');

        $ron = $run->results->firstWhere('currency_code', 'RON');
        $usd = $run->results->firstWhere('currency_code', 'USD');

        $this->assertNotNull($ron);
        $this->assertSame('paid_search', $ron->source_category);
        $this->assertSame(1, $ron->acquired_customer_count);
        $this->assertSame('100.00', $ron->acquisition_cost_amount);
        $this->assertSame('100.00', $ron->cac_amount);
        $this->assertSame('70.00', $ron->revenue_amount);
        $this->assertSame('70.00', $ron->ltv_amount);
        $this->assertSame(1, $ron->revenue_coverage_count);
        $this->assertSame(1, $ron->retention_coverage_count);

        $this->assertNotNull($usd);
        $this->assertNull($usd->cac_amount);
        $this->assertSame('50.00', $usd->ltv_amount);
        $this->assertSame(1, $usd->acquired_customer_count);

        $this->assertContains('duplicate_converted_lead', array_column($run->exceptions, 'type'));
        $this->assertContains('shared_or_unattributed_acquisition_cost', array_column($run->exceptions, 'type'));
        $this->assertContains($firstLead->getKey(), $ron->source_snapshot['lead_ids']);
        $this->assertSame('operational-cac-ltv-framework-v1', $run->policy_version);
    }

    public function test_missing_conversion_event_unknown_attribution_and_mismatched_customer_links_are_excluded(): void
    {
        $missingEventCustomer = Customer::factory()->create();
        $missingEventLead = CustomerLead::factory()->create();
        DB::table('customer_leads')->where('id', $missingEventLead->getKey())->update([
            'status' => 'converted',
            'converted_customer_id' => $missingEventCustomer->getKey(),
            'converted_at' => '2026-10-05 10:00:00',
        ]);

        $unknownCustomer = Customer::factory()->create();
        $this->convertedLead($unknownCustomer, 'unknown', '2026-10-06');

        $actualCustomer = Customer::factory()->create();
        $wrongCustomer = Customer::factory()->create();
        $this->convertedLead($actualCustomer, 'paid_search', '2026-10-07', (int) $wrongCustomer->getKey());

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');

        $types = array_column($run->exceptions, 'type');
        $this->assertContains('missing_conversion_attribution_event', $types);
        $this->assertContains('unknown_or_missing_acquisition_attribution', $types);
        $this->assertContains('missing_or_mismatched_conversion_link', $types);
        $this->assertCount(0, $run->results);
    }

    public function test_ltv_is_withheld_when_any_acquired_customer_has_no_revenue_source(): void
    {
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();
        $this->convertedLead($customerA, 'referral', '2026-10-05');
        $this->convertedLead($customerB, 'referral', '2026-10-06');
        $this->cost('referral', '200.00');
        $this->revenue($customerA, '2026-10-10', '75.00');

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame(2, $result->acquired_customer_count);
        $this->assertSame('100.00', $result->cac_amount);
        $this->assertSame(1, $result->revenue_coverage_count);
        $this->assertSame(1, $result->revenue_unknown_count);
        $this->assertNull($result->ltv_amount);
        $this->assertSame('incomplete', $result->result_status);
    }

    public function test_conversion_and_revenue_period_boundaries_are_applied_without_future_rows(): void
    {
        $included = Customer::factory()->create();
        $excluded = Customer::factory()->create();
        $this->convertedLead($included, 'event', '2026-10-31');
        $this->convertedLead($excluded, 'event', '2026-11-01');
        $this->cost('event', '10.00');
        $this->revenue($included, '2026-10-31', '25.00');
        $this->revenue($included, '2026-11-01', '999.00');

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame(1, $result->acquired_customer_count);
        $this->assertSame('25.00', $result->revenue_amount);
        $this->assertSame('25.00', $result->ltv_amount);
    }

    public function test_soft_deleted_invoice_link_is_reported_and_ltv_is_withheld(): void
    {
        $customer = Customer::factory()->create();
        $this->convertedLead($customer, 'referral', '2026-10-05');
        $this->cost('referral', '40.00');
        $invoice = CustomerInvoice::factory()->create(['customer_id' => $customer->getKey()]);
        ProductRevenue::query()->create([
            'product_id' => Product::factory()->create([
                'productable_type' => 'course',
                'productable_id' => 1,
            ])->getKey(),
            'customer_invoice_id' => $invoice->getKey(),
            'month' => '2026-10-10',
            'amount' => 10000,
            'amount_decimal' => '40.00',
            'currency_code' => 'RON',
        ]);
        $invoice->delete();

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertNull($result->ltv_amount);
        $this->assertSame(1, $result->revenue_unknown_count);
        $this->assertContains('revenue_missing_invoice_link', array_column($run->exceptions, 'type'));
    }

    public function test_ltv_is_withheld_when_explicit_retention_evidence_is_missing(): void
    {
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();
        $customerC = Customer::factory()->create();
        $this->convertedLead($customerA, 'referral', '2026-10-05');
        $this->convertedLead($customerB, 'referral', '2026-10-06', null, false);
        $this->convertedLead($customerC, 'referral', '2026-10-07', null, false);
        app(CustomerUnitEconomicsService::class)->recordRetentionObservation([
            'customer_id' => (int) $customerC->getKey(),
            'observed_on' => '2026-10-20',
            'state' => 'unknown',
            'source_reference' => 'retention-unknown:'.$customerC->getKey(),
            'evidence_reference' => 'retention-unknown-evidence:'.$customerC->getKey(),
        ]);
        $this->cost('referral', '300.00');
        $this->revenue($customerA, '2026-10-10', '75.00');
        $this->revenue($customerB, '2026-10-10', '90.00');
        $this->revenue($customerC, '2026-10-10', '100.00');

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame(1, $result->retention_coverage_count);
        $this->assertSame(2, $result->retention_unknown_count);
        $this->assertNull($result->ltv_amount);
        $types = array_column($run->exceptions, 'type');
        $this->assertContains('missing_retention_observation', $types);
        $this->assertContains('unusable_retention_observation', $types);
    }

    public function test_retained_observation_does_not_extend_revenue_beyond_its_observed_date(): void
    {
        $customer = Customer::factory()->create();
        $this->convertedLead($customer, 'event', '2026-10-05', null, false);
        app(CustomerUnitEconomicsService::class)->recordRetentionObservation([
            'customer_id' => (int) $customer->getKey(),
            'observed_on' => '2026-10-15',
            'state' => 'retained',
            'source_reference' => 'crm-retention:'.$customer->getKey(),
            'evidence_reference' => 'crm-retention-evidence:'.$customer->getKey(),
        ]);
        $this->cost('event', '20.00');
        $this->revenue($customer, '2026-10-10', '30.00');
        $this->revenue($customer, '2026-10-20', '40.00');

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame('30.00', $result->revenue_amount);
        $this->assertSame('30.00', $result->ltv_amount);
    }

    public function test_reactivation_observation_is_not_used_without_an_approved_reactivation_rule(): void
    {
        $customer = Customer::factory()->create();
        $this->convertedLead($customer, 'event', '2026-10-05', null, false);
        app(CustomerUnitEconomicsService::class)->recordRetentionObservation([
            'customer_id' => (int) $customer->getKey(),
            'observed_on' => '2026-10-20',
            'state' => 'reactivated',
            'source_reference' => 'crm-reactivation:'.$customer->getKey(),
            'evidence_reference' => 'crm-reactivation-evidence:'.$customer->getKey(),
        ]);
        $this->cost('event', '20.00');
        $this->revenue($customer, '2026-10-10', '30.00');

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame(0, $result->retention_coverage_count);
        $this->assertNull($result->ltv_amount);
        $this->assertContains('reactivation_policy_unapproved', array_column($run->exceptions, 'type'));
    }

    public function test_retention_observations_from_a_different_policy_version_are_not_used(): void
    {
        $customer = Customer::factory()->create();
        $this->convertedLead($customer, 'event', '2026-10-05');
        $this->cost('event', '20.00');
        $this->revenue($customer, '2026-10-10', '30.00');
        config(['customer-unit-economics.retention_policy_version' => 'test-retention-v2']);

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame(0, $result->retention_coverage_count);
        $this->assertNull($result->ltv_amount);
        $this->assertContains('unusable_retention_observation', array_column($run->exceptions, 'type'));
    }

    public function test_churn_observation_caps_lifetime_revenue_at_the_explicit_churn_date(): void
    {
        $customer = Customer::factory()->create();
        $this->convertedLead($customer, 'event', '2026-10-05', null, false);
        app(CustomerUnitEconomicsService::class)->recordRetentionObservation([
            'customer_id' => (int) $customer->getKey(),
            'observed_on' => '2026-10-15',
            'state' => 'churned',
            'source_reference' => 'crm-retention:'.$customer->getKey(),
            'evidence_reference' => 'crm-evidence:'.$customer->getKey(),
        ]);
        $this->cost('event', '20.00');
        $this->revenue($customer, '2026-10-10', '30.00');
        $this->revenue($customer, '2026-10-20', '40.00');

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();

        $this->assertSame('30.00', $result->revenue_amount);
        $this->assertSame('30.00', $result->ltv_amount);
        $this->assertSame(1, $result->retention_coverage_count);
    }

    public function test_unapproved_customer_level_policies_withhold_cac_and_ltv_values(): void
    {
        config([
            'customer-unit-economics.attribution_model' => 'unapproved',
            'customer-unit-economics.deduplication' => 'unapproved',
            'customer-unit-economics.cac_basis' => 'unapproved',
            'customer-unit-economics.revenue_basis' => 'unapproved',
            'customer-unit-economics.ltv_window' => 'unapproved',
            'customer-unit-economics.retention_basis' => 'unapproved',
            'customer-unit-economics.retention_policy_version' => 'unapproved',
        ]);

        $customer = Customer::factory()->create();
        $this->convertedLead($customer, 'paid_search', '2026-10-05');
        $this->cost('paid_search', '100.00');
        $this->revenue($customer, '2026-10-10', '80.00');

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $result = $run->results->firstOrFail();
        $types = array_column($run->exceptions, 'type');

        $this->assertSame(0, $result->acquired_customer_count);
        $this->assertSame('100.00', $result->acquisition_cost_amount);
        $this->assertNull($result->cac_amount);
        $this->assertNull($result->ltv_amount);
        $this->assertContains('acquisition_attribution_policy_unapproved', $types);
        $this->assertContains('cac_cost_policy_unapproved', $types);
        $this->assertContains('ltv_revenue_policy_unapproved', $types);
        $this->assertContains('retention_policy_unapproved', $types);
    }

    public function test_acquisition_cost_requires_positive_amount_currency_and_evidence_references(): void
    {
        $this->expectException(ValidationException::class);

        app(CustomerUnitEconomicsService::class)->recordAcquisitionCost([
            'incurred_on' => '2026-10-01',
            'source_category' => 'paid_search',
            'amount_decimal' => '0',
            'currency_code' => 'RON',
            'source_reference' => '',
            'evidence_reference' => '',
        ]);
    }

    public function test_acquisition_cost_entries_and_calculation_runs_are_immutable(): void
    {
        $entry = $this->cost('paid_search', '10.00');
        try {
            $entry->update(['amount_decimal' => '20.00']);
            $this->fail('Expected acquisition cost evidence to be immutable.');
        } catch (LogicException) {
            $this->assertSame('10.00', $entry->fresh()->amount_decimal);
        }

        $customer = Customer::factory()->create();
        $observation = app(CustomerUnitEconomicsService::class)->recordRetentionObservation([
            'customer_id' => (int) $customer->getKey(),
            'observed_on' => '2026-10-10',
            'state' => 'retained',
            'source_reference' => 'retention-source:immutable',
            'evidence_reference' => 'retention-evidence:immutable',
        ]);
        try {
            $observation->update(['state' => 'churned']);
            $this->fail('Expected retention observations to be immutable.');
        } catch (LogicException) {
            $this->assertSame('retained', $observation->fresh()->state);
        }

        $run = app(CustomerUnitEconomicsService::class)->calculate('2026-10-01', '2026-10-31');
        $this->expectException(LogicException::class);
        $run->update(['policy_version' => 'changed']);
    }
}
