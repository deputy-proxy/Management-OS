<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class KpiAlertEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['kpi_alert_id', 'actor_id', 'event', 'from_status', 'to_status', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('KPI alert lifecycle events are append-only.'));
        static::deleting(fn () => throw new LogicException('KPI alert lifecycle events are append-only.'));
    }

    /** @return BelongsTo<KpiAlert, $this> */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(KpiAlert::class, 'kpi_alert_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
