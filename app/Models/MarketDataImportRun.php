<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MarketDataImportRun extends Model
{
    protected $fillable = [
        'source_key', 'status', 'attempt_count', 'records_received', 'records_imported',
        'records_skipped', 'error_type', 'error_message', 'is_retryable', 'request_metadata',
        'started_at', 'finished_at', 'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer', 'records_received' => 'integer',
            'records_imported' => 'integer', 'records_skipped' => 'integer',
            'is_retryable' => 'boolean', 'request_metadata' => 'array',
            'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            if ($run->getOriginal('status') !== 'pending') {
                throw new LogicException('Completed market data import runs are immutable audit records.');
            }
            if ($run->isDirty(['source_key', 'requested_by', 'started_at', 'request_metadata'])) {
                throw new LogicException('Market data import request identity is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Market data import runs are retained as audit records.'));
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<MarketTrend, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(MarketTrend::class, 'source_import_run_id');
    }
}
