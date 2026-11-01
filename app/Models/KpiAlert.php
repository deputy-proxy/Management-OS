<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class KpiAlert extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'kpi_definition_id', 'owner_id', 'dedupe_key', 'severity', 'status',
        'observed_value', 'trigger_threshold', 'evidence', 'evidence_url',
        'first_seen_at', 'last_seen_at', 'acknowledged_at', 'acknowledged_by',
        'resolved_at', 'resolved_by', 'cooldown_until',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'observed_value' => 'decimal:6',
            'trigger_threshold' => 'decimal:6',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'cooldown_until' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('KPI alerts are retained as lifecycle records and cannot be deleted.'));
    }

    /** @return BelongsTo<KpiDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return HasMany<KpiAlertEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(KpiAlertEvent::class)->orderBy('created_at')->orderBy('id');
    }
}
