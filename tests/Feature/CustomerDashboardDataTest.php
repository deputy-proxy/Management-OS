<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerInteraction;
use App\Models\CustomerInvoice;
use App\Models\CustomerLead;
use App\Models\Task;
use App\Models\User;
use App\Services\CustomerDashboardData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_separates_converted_leads_and_counts_current_contracts_and_recent_interactions(): void
    {
        $now = CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone'));
        $customer = Customer::factory()->create();
        Customer::factory()->create()->delete();
        CustomerLead::factory()->create(['status' => 'New', 'converted_customer_id' => null, 'created_at' => '2026-10-10 10:00:00']);
        CustomerLead::factory()->create(['status' => 'New', 'converted_customer_id' => $customer->id]);
        CustomerLead::factory()->create(['status' => 'Qualified', 'converted_customer_id' => null])->delete();

        CustomerContract::factory()->create(['customer_id' => $customer->id, 'start_date' => '2026-01-01', 'end_date' => '2026-10-17']);
        CustomerContract::factory()->create(['customer_id' => $customer->id, 'start_date' => '2026-10-18', 'end_date' => '2027-10-17']);
        CustomerContract::factory()->create(['customer_id' => $customer->id, 'start_date' => '2025-01-01', 'end_date' => '2026-10-16']);

        CustomerInteraction::factory()->create(['customer_id' => $customer->id, 'timestamp' => '2026-09-18 12:00:00', 'reason' => 'Boundary']);
        CustomerInteraction::factory()->create(['customer_id' => $customer->id, 'timestamp' => '2026-09-17 11:59:59', 'reason' => 'Too old']);
        CustomerInteraction::factory()->create(['customer_id' => $customer->id, 'timestamp' => '2026-10-18 12:00:00', 'reason' => 'Future']);
        CustomerInteraction::factory()->create(['customer_id' => $customer->id, 'timestamp' => '2026-10-01 12:00:00', 'reason' => 'Deleted'])->delete();

        $summary = app(CustomerDashboardData::class)->summary($now);

        $this->assertSame(1, $summary['customers']);
        $this->assertSame(1, $summary['open_leads']);
        $this->assertSame(1, $summary['active_contracts']);
        $this->assertSame(1, $summary['interactions_last_30_days']);
        $this->assertSame('October 17, 2026', $summary['as_of']);
        $this->assertCount(1, $summary['recent_leads']);
        $this->assertSame('Boundary', $summary['recent_interactions']->first()->reason);
        $this->assertSame([['status' => 'new', 'count' => 1]], $summary['lead_statuses']);
    }

    public function test_lead_attribution_summary_preserves_unknown_and_counts_campaign_and_referral_metadata(): void
    {
        CustomerLead::factory()->create(['source_category' => 'unknown']);
        CustomerLead::factory()->create(['source_category' => 'paid_search', 'campaign_name' => 'campaign-1']);
        CustomerLead::factory()->create(['source_category' => 'referral', 'referral_partner' => 'partner-1']);

        $summary = app(CustomerDashboardData::class)->summary(includeLeadAttribution: true);

        $this->assertSame(1, $summary['lead_attribution']['unknown_source_count']);
        $this->assertSame(1, $summary['lead_attribution']['campaign_tagged_count']);
        $this->assertSame(1, $summary['lead_attribution']['referral_tagged_count']);
        $this->assertSame([
            ['source_category' => 'paid_search', 'count' => 1],
            ['source_category' => 'referral', 'count' => 1],
            ['source_category' => 'unknown', 'count' => 1],
        ], $summary['lead_attribution']['source_categories']);
    }

    public function test_lead_attribution_is_not_exposed_without_lead_list_permission(): void
    {
        $summary = app(CustomerDashboardData::class)->summary();
        $this->assertNull($summary['lead_attribution']);
    }

    public function test_retention_cohorts_count_unique_customers_and_use_explicit_lifecycle_events(): void
    {
        $now = CarbonImmutable::parse('2026-11-30 12:00:00', 'Europe/Bucharest');
        Carbon::setTestNow($now);
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        CustomerLead::factory()->create(['status' => 'converted', 'converted_customer_id' => $customer->id, 'converted_at' => '2026-11-02 10:00:00']);
        CustomerLead::factory()->create(['status' => 'converted', 'converted_customer_id' => $customer->id, 'converted_at' => '2026-11-04 10:00:00']);
        CustomerLead::factory()->create(['status' => 'converted', 'converted_customer_id' => $other->id, 'converted_at' => '2026-11-03 10:00:00']);
        $customer->lifecycleEvents()->create(['event_type' => 'churned', 'effective_at' => '2026-11-20 10:00:00', 'reason' => 'Ended', 'evidence_reference' => 'case-1']);
        $customer->lifecycleEvents()->create(['event_type' => 'reactivated', 'effective_at' => '2026-11-25 10:00:00', 'reason' => 'Returned', 'evidence_reference' => 'case-2']);

        $summary = app(CustomerDashboardData::class)->summary($now, includeRetentionCohorts: true);
        $cohort = $summary['retention_cohorts']['cohorts'][0];

        $this->assertSame('2026-11', $cohort['cohort_month']);
        $this->assertSame('2026-11', $cohort['period_month']);
        $this->assertTrue($cohort['is_partial_period']);
        $this->assertSame(2, $cohort['cohort_size']);
        $this->assertSame(2, $cohort['active_count']);
        $this->assertSame(0, $cohort['churned_count']);
        $this->assertSame(100.0, $cohort['retention_percent']);
        $this->assertSame(1, $summary['retention_cohorts']['duplicate_conversion_leads_excluded_count']);
        Carbon::setTestNow();
    }

    public function test_retention_cohorts_are_not_exposed_without_both_customer_and_lead_permissions(): void
    {
        $summary = app(CustomerDashboardData::class)->summary();
        $this->assertNull($summary['retention_cohorts']);
    }

    public function test_invoice_status_counts_are_limited_to_current_billing_month_and_exclude_soft_deleted_rows(): void
    {
        $now = CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone'));
        CustomerInvoice::factory()->create(['month' => '2026-10-01', 'status' => 'Sent']);
        CustomerInvoice::factory()->create(['month' => '2026-10-15', 'status' => 'Sent']);
        CustomerInvoice::factory()->create(['month' => '2026-10-01', 'status' => 'Sent'])->delete();
        CustomerInvoice::factory()->create(['month' => '2026-09-01', 'status' => 'Paid']);

        $summary = app(CustomerDashboardData::class)->summary($now);

        $this->assertSame([['status' => 'Sent', 'count' => 2]], $summary['invoice_statuses']);
    }

    public function test_follow_up_tasks_are_scoped_and_contract_end_dates_are_permission_gated(): void
    {
        $now = CarbonImmutable::parse('2026-10-25 12:00:00', config('app.timezone'));
        $viewer = User::factory()->create();
        $otherUser = User::factory()->create();
        $lead = CustomerLead::factory()->create(['status' => 'contacted']);
        $contract = CustomerContract::factory()->create(['contract_code' => 'REVIEW-1', 'end_date' => '2026-11-01']);

        $this->actingAs($viewer);
        Task::factory()->create([
            'action' => 'Call lead', 'status' => 'pending', 'due_date' => '2026-10-20',
            'taskable_id' => $lead->id, 'taskable_type' => CustomerLead::class,
            'assigned_to' => $viewer->id, 'created_by' => $otherUser->id,
        ]);
        $this->actingAs($otherUser);
        Task::factory()->create([
            'action' => 'Other user task', 'status' => 'pending', 'due_date' => '2026-10-21',
            'taskable_id' => $lead->id, 'taskable_type' => CustomerLead::class,
            'assigned_to' => $otherUser->id, 'created_by' => $otherUser->id,
        ]);
        $this->actingAs($viewer);
        Task::factory()->create([
            'action' => 'Completed task', 'status' => 'done', 'due_date' => '2026-10-19',
            'taskable_id' => $contract->id, 'taskable_type' => CustomerContract::class,
            'assigned_to' => $viewer->id, 'created_by' => $viewer->id,
        ]);

        $summary = app(CustomerDashboardData::class)->summary($now, true, false, $viewer->id, true);

        $this->assertCount(1, $summary['follow_up_tasks']);
        $this->assertSame('Call lead', $summary['follow_up_tasks']->first()->action);
        $this->assertSame('REVIEW-1', $summary['contract_end_dates']->first()->contract_code);

        $restricted = app(CustomerDashboardData::class)->summary($now);
        $this->assertCount(0, $restricted['follow_up_tasks']);
        $this->assertCount(0, $restricted['contract_end_dates']);
    }

    public function test_summary_handles_empty_data(): void
    {
        $summary = app(CustomerDashboardData::class)->summary(CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone')));

        $this->assertSame(0, $summary['customers']);
        $this->assertSame(0, $summary['open_leads']);
        $this->assertSame(0, $summary['active_contracts']);
        $this->assertSame(0, $summary['interactions_last_30_days']);
        $this->assertSame([], $summary['invoice_statuses']);
        $this->assertSame([], $summary['lead_statuses']);
        $this->assertCount(0, $summary['recent_interactions']);
        $this->assertCount(0, $summary['recent_leads']);
    }
}
