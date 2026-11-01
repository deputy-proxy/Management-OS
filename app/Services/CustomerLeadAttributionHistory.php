<?php

namespace App\Services;

use App\Models\CustomerLead;
use App\Models\CustomerLeadAttributionEvent;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

class CustomerLeadAttributionHistory
{
    /** @var list<string> */
    private const ATTRIBUTION_FIELDS = [
        'source_category', 'source', 'campaign_name', 'referral_partner',
        'attribution_policy_version', 'source_captured_at',
    ];

    public function recordFirstTouch(CustomerLead $lead): CustomerLeadAttributionEvent
    {
        return CustomerLeadAttributionEvent::query()->firstOrCreate(
            ['idempotency_key' => "lead:{$lead->getKey()}:first-touch"],
            [
                'customer_lead_id' => $lead->getKey(),
                'event_type' => 'first_touch',
                'snapshot' => $this->snapshot($lead),
                'occurred_at' => $lead->source_captured_at ?? $lead->created_at ?? now(),
                'recorded_by' => auth()->id(),
            ],
        );
    }

    public function recordAttributionChange(CustomerLead $lead): CustomerLeadAttributionEvent
    {
        return CustomerLeadAttributionEvent::query()->create([
            'customer_lead_id' => $lead->getKey(),
            'event_type' => 'attribution_updated',
            'idempotency_key' => 'lead:'.$lead->getKey().':update:'.Str::uuid(),
            'snapshot' => $this->snapshot($lead),
            'occurred_at' => now(),
            'recorded_by' => auth()->id(),
        ]);
    }

    public function recordConversion(CustomerLead $lead): CustomerLeadAttributionEvent
    {
        $history = CustomerLeadAttributionEvent::query()
            ->where('customer_lead_id', $lead->getKey())
            ->whereIn('event_type', ['first_touch', 'attribution_updated'])
            ->orderBy('occurred_at')
            ->orderBy('id');
        $firstTouch = (clone $history)->first();
        $lastTouch = (clone $history)->reorder()->latest('occurred_at')->latest('id')->first();

        return CustomerLeadAttributionEvent::query()->firstOrCreate(
            ['idempotency_key' => "lead:{$lead->getKey()}:conversion"],
            [
                'customer_lead_id' => $lead->getKey(),
                'event_type' => 'conversion',
                'snapshot' => [
                    'first_touch' => $firstTouch === null ? null : $firstTouch->snapshot,
                    'last_touch' => $lastTouch === null ? $this->snapshot($lead) : $lastTouch->snapshot,
                    'conversion' => [
                        'customer_id' => $lead->converted_customer_id,
                        'converted_at' => $this->isoTimestamp($lead->getAttribute('converted_at')),
                    ],
                ],
                'occurred_at' => $lead->converted_at ?? now(),
                'recorded_by' => auth()->id(),
            ],
        );
    }

    public function hasAttributionChanged(CustomerLead $lead): bool
    {
        return $lead->wasChanged(self::ATTRIBUTION_FIELDS);
    }

    /** @return array<string, mixed> */
    private function snapshot(CustomerLead $lead): array
    {
        return [
            'source_category' => $lead->source_category ?: 'unknown',
            'source' => $lead->source,
            'campaign_name' => $lead->campaign_name,
            'referral_partner' => $lead->referral_partner,
            'attribution_policy_version' => $lead->attribution_policy_version,
            'source_captured_at' => $this->isoTimestamp($lead->getAttribute('source_captured_at')),
        ];
    }

    private function isoTimestamp(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)->toISOString()
            : null;
    }
}
