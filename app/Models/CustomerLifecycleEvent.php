<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

class CustomerLifecycleEvent extends Model
{
    public const TYPE_CHURNED = 'churned';

    public const TYPE_REACTIVATED = 'reactivated';

    public $timestamps = false;

    protected $fillable = [
        'event_type', 'effective_at', 'reason', 'evidence_reference', 'recorded_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (! in_array($event->event_type, [self::TYPE_CHURNED, self::TYPE_REACTIVATED], true)) {
                throw ValidationException::withMessages(['event_type' => 'Choose a supported customer lifecycle event.']);
            }

            if (! $event->effective_at || Carbon::parse($event->effective_at)->isFuture()) {
                throw ValidationException::withMessages(['effective_at' => 'Lifecycle events require an actual effective timestamp no later than now.']);
            }

            if (blank($event->reason) || blank($event->evidence_reference)) {
                throw ValidationException::withMessages(['evidence_reference' => 'A reason and source-evidence reference are required.']);
            }

            $activation = CustomerLead::query()
                ->where('converted_customer_id', $event->customer_id)
                ->where('status', 'converted')
                ->whereNotNull('converted_at')
                ->orderBy('converted_at')
                ->orderBy('id')
                ->value('converted_at');

            $trackingStart = config('customer-retention.tracking_start_at', '2026-10-27 00:00:00');
            if (! $activation || Carbon::parse($activation)->lt(Carbon::parse($trackingStart)) || Carbon::parse($event->effective_at)->lt(Carbon::parse($activation))) {
                throw ValidationException::withMessages(['customer_id' => 'Lifecycle events require a verified conversion cohort within the active tracking policy.']);
            }

            $latest = self::query()
                ->where('customer_id', $event->customer_id)
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->first();

            if ($latest && Carbon::parse($event->effective_at)->lt(Carbon::parse($latest->effective_at))) {
                throw ValidationException::withMessages(['effective_at' => 'Lifecycle events must be recorded in effective-date order.']);
            }

            $currentState = $latest?->event_type === self::TYPE_CHURNED ? 'churned' : 'active';
            if (($event->event_type === self::TYPE_CHURNED && $currentState === 'churned')
                || ($event->event_type === self::TYPE_REACTIVATED && $currentState !== 'churned')) {
                throw ValidationException::withMessages(['event_type' => 'Lifecycle events must alternate between churned and reactivated states.']);
            }

            $event->recorded_by ??= auth()->id();
            $event->recorded_at ??= now();
        });

        static::updating(fn () => throw new LogicException('Customer lifecycle events are immutable; record a new event to correct history.'));
        static::deleting(fn () => throw new LogicException('Customer lifecycle events are retained as audit history and cannot be deleted.'));
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
