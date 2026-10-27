<?php

namespace Tests\Feature\Filament;

use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\CustomerLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerLeadLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_statuses_are_normalized_and_limited_to_the_documented_lifecycle(): void
    {
        $lead = CustomerLead::factory()->create(['status' => 'Qualified']);
        $this->assertSame('qualified', $lead->status);

        $this->expectException(ValidationException::class);
        $lead->update(['status' => 'something arbitrary']);
    }

    public function test_source_attribution_uses_a_controlled_category_and_preserves_legacy_source_text(): void
    {
        $lead = CustomerLead::factory()->create([
            'source' => 'Old free-text source from legacy import',
            'source_category' => 'unknown',
        ]);

        $this->assertSame('unknown', $lead->source_category);
        $this->assertSame('Old free-text source from legacy import', $lead->source);

        $lead->update([
            'source_category' => 'paid_search',
            'campaign_name' => 'spring-campaign-01',
            'source_captured_at' => now(),
        ]);

        $this->assertSame('paid_search', $lead->fresh()->source_category);
        $this->assertSame('Old free-text source from legacy import', $lead->fresh()->source);
        $this->assertSame('spring-campaign-01', $lead->fresh()->campaign_name);
        $this->assertSame('v1', $lead->fresh()->attribution_policy_version);
    }

    public function test_invalid_source_attribution_category_is_rejected(): void
    {
        $lead = CustomerLead::factory()->create();

        $this->expectException(ValidationException::class);
        $lead->update(['source_category' => 'invented-channel']);
    }

    public function test_status_transitions_follow_the_lifecycle(): void
    {
        $lead = CustomerLead::factory()->create(['status' => 'new']);
        $lead->update(['status' => 'contacted']);
        $lead->update(['status' => 'proposal']);

        try {
            $lead->update(['status' => 'contacted']);
            $this->fail('A proposal-stage lead cannot move backwards to contacted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $lead->update(['status' => 'lost']);
        $lead->update(['status' => 'new']);
        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_converted_status_requires_conversion_tracking_and_is_terminal(): void
    {
        $lead = CustomerLead::factory()->create();

        try {
            $lead->update(['status' => 'converted']);
            $this->fail('A lead cannot be marked converted without a linked customer and timestamp.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $customer = Customer::factory()->create();
        $lead->forceFill(['status' => 'converted', 'converted_customer_id' => $customer->id, 'converted_at' => now()])->save();

        $this->expectException(ValidationException::class);
        $lead->update(['status' => 'qualified']);
    }

    public function test_status_history_records_initial_status_and_each_valid_transition_without_logging_unrelated_edits(): void
    {
        $lead = CustomerLead::factory()->create(['status' => 'new']);

        $this->assertSame(1, $lead->statusHistory()->count());
        $this->assertNull($lead->statusHistory()->first()->from_status);
        $this->assertSame('new', $lead->statusHistory()->first()->to_status);

        $lead->update(['status' => 'contacted']);
        $lead->update(['phone' => '+40123456789']);

        $history = $lead->fresh()->statusHistory;
        $this->assertCount(2, $history);
        $this->assertSame('new', $history[1]->from_status);
        $this->assertSame('contacted', $history[1]->to_status);
        $this->assertNotNull($history[1]->changed_at);
    }

    public function test_customer_interactions_are_reachable_from_a_converted_lead(): void
    {
        $customer = Customer::factory()->create();
        $lead = CustomerLead::factory()->create([
            'status' => 'converted',
            'converted_customer_id' => $customer->id,
            'converted_at' => now(),
        ]);
        $interaction = CustomerInteraction::factory()->create(['customer_id' => $customer->id]);

        $this->assertTrue($lead->customerInteractions->contains('id', $interaction->id));
        $this->assertTrue($customer->sourceLeads->contains('id', $lead->id));
    }
}
