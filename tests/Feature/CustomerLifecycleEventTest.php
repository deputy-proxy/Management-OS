<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class CustomerLifecycleEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_events_require_a_verified_conversion_and_evidence(): void
    {
        $customer = Customer::factory()->create();

        try {
            $customer->lifecycleEvents()->create([
                'event_type' => 'churned', 'effective_at' => now()->subMinutes(30),
                'reason' => 'Service ended', 'evidence_reference' => 'case-123',
            ]);
            $this->fail('A lifecycle event without a conversion cohort must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }

        CustomerLead::factory()->create([
            'status' => 'converted', 'converted_customer_id' => $customer->id,
            'converted_at' => now()->subMinutes(45),
        ]);
        $event = $customer->lifecycleEvents()->create([
            'event_type' => 'churned', 'effective_at' => now()->subMinutes(15),
            'reason' => 'Service ended', 'evidence_reference' => 'case-123',
        ]);

        $this->assertSame($customer->id, $event->customer_id);
        $this->assertNotNull($event->recorded_at);
        $this->assertNotNull($event->id);
    }

    public function test_lifecycle_events_must_alternate_churn_and_reactivation(): void
    {
        $customer = Customer::factory()->create();
        CustomerLead::factory()->create([
            'status' => 'converted', 'converted_customer_id' => $customer->id,
            'converted_at' => now()->subHours(5),
        ]);

        $customer->lifecycleEvents()->create([
            'event_type' => 'churned', 'effective_at' => now()->subHours(3),
            'reason' => 'Cancelled', 'evidence_reference' => 'case-1',
        ]);
        $customer->lifecycleEvents()->create([
            'event_type' => 'reactivated', 'effective_at' => now()->subHours(2),
            'reason' => 'Renewed', 'evidence_reference' => 'contract-2',
        ]);

        $this->expectException(ValidationException::class);
        $customer->lifecycleEvents()->create([
            'event_type' => 'reactivated', 'effective_at' => now()->subHour(),
            'reason' => 'Duplicate reactivation', 'evidence_reference' => 'contract-3',
        ]);
    }

    public function test_lifecycle_event_history_is_immutable(): void
    {
        $customer = Customer::factory()->create();
        CustomerLead::factory()->create([
            'status' => 'converted', 'converted_customer_id' => $customer->id,
            'converted_at' => now()->subHours(5),
        ]);
        $event = $customer->lifecycleEvents()->create([
            'event_type' => 'churned', 'effective_at' => now()->subHour(),
            'reason' => 'Cancelled', 'evidence_reference' => 'case-1',
        ]);

        try {
            $event->update(['reason' => 'Rewritten']);
            $this->fail('Lifecycle event records must not be rewritten.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }
}
