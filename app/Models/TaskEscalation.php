<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TaskEscalation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    protected $fillable = [
        'task_id', 'due_date', 'level', 'status', 'owner_id', 'reason',
        'escalated_at', 'acknowledged_at', 'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'escalated_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $escalation): void {
            if ($escalation->status !== self::STATUS_OPEN || $escalation->level !== 'overdue') {
                throw new LogicException('New task escalations must begin as open overdue records.');
            }
        });

        static::updating(function (self $escalation): void {
            $originalStatus = $escalation->getOriginal('status');
            if ($originalStatus === self::STATUS_ACKNOWLEDGED && $escalation->status !== self::STATUS_ACKNOWLEDGED) {
                throw new LogicException('Acknowledged task escalations cannot be reopened or rewritten.');
            }
            $changed = array_diff(array_keys($escalation->getDirty()), ['status', 'acknowledged_at', 'acknowledged_by', 'updated_at']);
            if ($changed !== []) {
                throw new LogicException('Task escalation evidence is immutable.');
            }
            if ($escalation->status === self::STATUS_ACKNOWLEDGED && (! $escalation->acknowledged_at || ! $escalation->acknowledged_by)) {
                throw new LogicException('Acknowledging an escalation requires an actor and timestamp.');
            }
            if ($originalStatus !== self::STATUS_OPEN || ! in_array($escalation->status, [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED], true)) {
                throw new LogicException('Only open task escalations can be acknowledged.');
            }
        });

        static::deleting(fn () => throw new LogicException('Task escalation records are audit evidence and cannot be deleted.'));
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
