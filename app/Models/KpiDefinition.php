<?php

namespace App\Models;

use App\Services\KpiCatalogueRegistry;
use App\Services\KpiDefinitionConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

class KpiDefinition extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'key', 'version', 'label', 'description', 'domain', 'calculation_type', 'unit', 'source',
        'date_basis', 'permission', 'missing_data', 'filters', 'dimensions', 'aggregation', 'period',
        'target_value', 'warning_threshold', 'critical_threshold', 'threshold_direction',
        'effective_from', 'effective_until', 'status', 'created_by', 'approved_by', 'approved_at',
        'retired_by', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'filters' => 'array', 'dimensions' => 'array', 'period' => 'array',
            'target_value' => 'decimal:6', 'warning_threshold' => 'decimal:6', 'critical_threshold' => 'decimal:6',
            'effective_from' => 'date', 'effective_until' => 'date',
            'approved_at' => 'immutable_datetime', 'retired_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $definition): void {
            $catalogue = app(KpiCatalogueRegistry::class)->get($definition->key);
            if (! is_array($catalogue) || $catalogue['availability'] !== 'available') {
                throw ValidationException::withMessages(['key' => 'Choose a registered KPI key with an available source and calculation contract.']);
            }
            $definition->version = (int) self::query()->where('key', $definition->key)->max('version') + 1;
            foreach (['label', 'description', 'domain', 'calculation_type', 'unit', 'source', 'date_basis', 'permission', 'missing_data'] as $field) {
                $definition->{$field} = $catalogue[$field];
            }
            $definition->status = self::STATUS_DRAFT;
            $definition->threshold_direction ??= 'target_only';
            $definition->filters ??= [];
            $definition->dimensions ??= [];
            $definition->aggregation ??= 'count';
            $definition->period ??= ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false];
            $definition->created_by ??= auth()->id();
            $definition->effective_from ??= now()->toDateString();
        });

        static::saving(function (self $definition): void {
            $definition->threshold_direction ??= 'target_only';
            app(KpiDefinitionConfiguration::class)->validate(
                (string) $definition->calculation_type,
                $definition->filters ?? [],
                $definition->dimensions ?? [],
                $definition->aggregation ?? 'count',
                $definition->period,
            );
            if (! in_array($definition->threshold_direction, ['higher_is_worse', 'lower_is_worse', 'target_only'], true)) {
                throw ValidationException::withMessages(['threshold_direction' => 'Choose a supported threshold direction.']);
            }
            foreach (['target_value', 'warning_threshold', 'critical_threshold'] as $field) {
                if ($definition->{$field} !== null && (float) $definition->{$field} < 0) {
                    throw ValidationException::withMessages([$field => 'KPI targets and thresholds cannot be negative.']);
                }
            }
            if ($definition->effective_until && Carbon::parse($definition->effective_until)->lt(Carbon::parse($definition->effective_from))) {
                throw ValidationException::withMessages(['effective_until' => 'Effective end must be on or after effective start.']);
            }
            if ($definition->threshold_direction === 'higher_is_worse' && $definition->warning_threshold !== null && $definition->critical_threshold !== null && (float) $definition->warning_threshold > (float) $definition->critical_threshold) {
                throw ValidationException::withMessages(['critical_threshold' => 'Critical threshold must be greater than or equal to the warning threshold.']);
            }
            if ($definition->threshold_direction === 'lower_is_worse' && $definition->warning_threshold !== null && $definition->critical_threshold !== null && (float) $definition->warning_threshold < (float) $definition->critical_threshold) {
                throw ValidationException::withMessages(['critical_threshold' => 'Critical threshold must be less than or equal to the warning threshold.']);
            }
        });

        static::updating(function (self $definition): void {
            $originalStatus = $definition->getOriginal('status');
            if ($originalStatus === self::STATUS_DRAFT) {
                if ($definition->status === self::STATUS_ACTIVE && ! hexa()->can('kpi_definition.approve')) {
                    throw new LogicException('KPI definition approval permission is required to activate a definition.');
                }
                if ($definition->status === self::STATUS_ACTIVE && (! $definition->approved_by || ! $definition->approved_at)) {
                    throw ValidationException::withMessages(['status' => 'Activation requires an approving user and timestamp.']);
                }
                return;
            }
            $dirty = array_keys($definition->getDirty());
            $changed = array_diff($dirty, ['effective_until', 'status', 'retired_by', 'retired_at', 'updated_at']);
            if ($changed !== [] || $originalStatus === self::STATUS_RETIRED || ! in_array($definition->status, [self::STATUS_ACTIVE, self::STATUS_RETIRED], true)) {
                throw new LogicException('Approved KPI definition semantics are immutable; create a new version instead.');
            }
            if (array_intersect($dirty, ['effective_until', 'status', 'retired_by', 'retired_at']) !== [] && ! hexa()->can('kpi_definition.approve')) {
                throw new LogicException('KPI definition approval permission is required to change an effective period.');
            }
            if ($definition->status === self::STATUS_RETIRED && (! $definition->retired_by || ! $definition->retired_at)) {
                throw ValidationException::withMessages(['status' => 'Retirement requires an actor and timestamp.']);
            }
        });
        static::deleting(fn () => throw new LogicException('KPI definitions are versioned audit records and cannot be deleted.'));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    /** @return BelongsTo<User, $this> */
    public function retirer(): BelongsTo { return $this->belongsTo(User::class, 'retired_by'); }
}
