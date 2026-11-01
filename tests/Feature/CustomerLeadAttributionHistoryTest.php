<?php

namespace Tests\Feature;

use App\Actions\ConvertCustomerLead;
use App\Models\CustomerLead;
use App\Models\CustomerLeadAttributionEvent;
use App\Services\CustomerLeadAttributionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

class CustomerLeadAttributionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_touch_is_preserved_and_each_attribution_change_is_appended_as_last_touch_history(): void
    {
        $lead = CustomerLead::factory()->create([
            'source' => 'legacy free-text value',
            'source_category' => 'unknown',
            'source_captured_at' => '2026-10-01 09:00:00',
        ]);

        $lead->update([
            'source_category' => 'paid_search',
            'campaign_name' => 'campaign-001',
            'source_captured_at' => '2026-10-05 09:00:00',
        ]);
        $lead->update([
            'source_category' => 'social',
            'referral_partner' => 'community-partner',
            'source_captured_at' => '2026-10-10 09:00:00',
        ]);

        $events = $lead->fresh()->attributionEvents;

        $this->assertCount(3, $events);
        $this->assertSame('first_touch', $events[0]->event_type);
        $this->assertSame('unknown', $events[0]->snapshot['source_category']);
        $this->assertSame('legacy free-text value', $events[0]->snapshot['source']);
        $this->assertSame('paid_search', $events[1]->snapshot['source_category']);
        $this->assertSame('social', $events[2]->snapshot['source_category']);
        $this->assertSame('community-partner', $events[2]->snapshot['referral_partner']);
        $this->assertSame('legacy free-text value', $lead->fresh()->source);
    }

    public function test_conversion_stores_separate_first_and_last_touch_snapshots_and_is_idempotent(): void
    {
        $lead = CustomerLead::factory()->create([
            'source_category' => 'organic_search',
            'source' => 'search engine',
            'source_captured_at' => '2026-10-01 09:00:00',
        ]);
        $lead->update([
            'source_category' => 'referral',
            'referral_partner' => 'partner-42',
            'source_captured_at' => '2026-10-15 12:00:00',
        ]);

        $customer = app(ConvertCustomerLead::class)->handle($lead, $this->conversionData());
        $conversionEvent = $lead->fresh()->attributionEvents->firstWhere('event_type', 'conversion');

        $this->assertNotNull($conversionEvent);
        $this->assertSame('organic_search', $conversionEvent->snapshot['first_touch']['source_category']);
        $this->assertSame('referral', $conversionEvent->snapshot['last_touch']['source_category']);
        $this->assertSame('partner-42', $conversionEvent->snapshot['last_touch']['referral_partner']);
        $this->assertSame($customer->getKey(), $conversionEvent->snapshot['conversion']['customer_id']);
        $this->assertSame($lead->fresh()->converted_at->toISOString(), $conversionEvent->snapshot['conversion']['converted_at']);

        app(CustomerLeadAttributionHistory::class)->recordConversion($lead->fresh());

        $this->assertSame(1, CustomerLeadAttributionEvent::query()
            ->where('customer_lead_id', $lead->getKey())
            ->where('event_type', 'conversion')
            ->count());
    }

    public function test_missing_source_remains_unknown_in_history_and_conversion_does_not_infer_a_channel(): void
    {
        $lead = CustomerLead::factory()->create([
            'source' => '',
            'source_category' => 'unknown',
            'campaign_name' => null,
            'referral_partner' => null,
            'source_captured_at' => null,
        ]);

        $customer = app(ConvertCustomerLead::class)->handle($lead, $this->conversionData());
        $conversion = $lead->fresh()->attributionEvents->firstWhere('event_type', 'conversion');

        $this->assertSame('unknown', $conversion->snapshot['first_touch']['source_category']);
        $this->assertSame('unknown', $conversion->snapshot['last_touch']['source_category']);
        $this->assertSame('', $conversion->snapshot['last_touch']['source']);
        $this->assertSame($customer->getKey(), $conversion->snapshot['conversion']['customer_id']);
    }

    public function test_lost_and_reopened_lead_lifecycle_does_not_rewrite_attribution_history(): void
    {
        $lead = CustomerLead::factory()->create(['source_category' => 'event']);
        $lead->update(['status' => 'lost']);
        $lead->update(['status' => 'new']);

        $this->assertSame('event', $lead->fresh()->source_category);
        $this->assertCount(1, $lead->fresh()->attributionEvents);
        $this->assertSame('event', $lead->fresh()->attributionEvents->first()->snapshot['source_category']);
    }

    public function test_attribution_history_is_append_only(): void
    {
        $lead = CustomerLead::factory()->create();
        $event = $lead->attributionEvents()->firstOrFail();

        try {
            $event->update(['event_type' => 'rewritten']);
            $this->fail('Attribution events must not be editable.');
        } catch (LogicException $exception) {
            $this->assertSame('Attribution history is append-only.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_source_captured_at_period_boundaries_are_kept_as_exact_timestamps(): void
    {
        Carbon::setTestNow('2026-11-02 00:00:00');
        $before = CustomerLead::factory()->create(['source_captured_at' => '2026-10-31 23:59:59']);
        $after = CustomerLead::factory()->create(['source_captured_at' => '2026-11-01 00:00:00']);

        $beforeEvent = $before->attributionEvents()->firstOrFail();
        $afterEvent = $after->attributionEvents()->firstOrFail();

        $this->assertSame('2026-10-31T23:59:59+02:00', $beforeEvent->occurred_at->toIso8601String());
        $this->assertSame('2026-11-01T00:00:00+02:00', $afterEvent->occurred_at->toIso8601String());
        Carbon::setTestNow();
    }

    /** @return array<string, mixed> */
    private function conversionData(): array
    {
        return [
            'customer_type' => 'Individual',
            'cnp_cui' => '1234567890123',
            'address' => '1 Example Street',
            'contract_code' => 'ATTRIBUTION-'.fake()->unique()->numerify('#####'),
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'document' => 'customer-contracts/signed.pdf',
            'confirm_signed' => true,
        ];
    }
}
