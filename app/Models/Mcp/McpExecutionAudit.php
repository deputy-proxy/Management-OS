<?php

namespace App\Models\Mcp;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
final class McpExecutionAudit extends Model
{
    protected $fillable = [
        'ai_principal_id',
        'human_initiator_id',
        'human_approver_id',
        'approval_id',
        'oauth_client_id',
        'event_type',
        'tool_name',
        'tool_version',
        'target_type',
        'target_id',
        'request_id',
        'idempotency_key',
        'payload_hash',
        'status',
        'result_code',
        'error_code',
        'domain_event_reference',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (self $audit): void {
            $originalStatus = $audit->getRawOriginal('status');
            $currentStatus = $audit->getAttributes()['status'] ?? null;
            if ($originalStatus !== $currentStatus
                && ($originalStatus !== 'started' || ! in_array($currentStatus, ['succeeded', 'failed', 'uncertain'], true))) {
                throw new LogicException('The MCP execution audit lifecycle transition is not permitted.');
            }

            foreach ([
                'ai_principal_id',
                'human_initiator_id',
                'approval_id',
                'oauth_client_id',
                'event_type',
                'tool_name',
                'tool_version',
                'target_type',
                'target_id',
                'request_id',
                'idempotency_key',
                'payload_hash',
                'started_at',
                'created_at',
            ] as $attribute) {
                if ($audit->getRawOriginal($attribute) !== ($audit->getAttributes()[$attribute] ?? null)) {
                    throw new LogicException('MCP execution audit identity and payload fields are immutable.');
                }
            }

            foreach (['human_approver_id', 'result_code', 'error_code', 'domain_event_reference', 'completed_at'] as $attribute) {
                $original = $audit->getRawOriginal($attribute);
                $current = $audit->getAttributes()[$attribute] ?? null;
                if ($original !== null && $original !== $current) {
                    throw new LogicException('MCP audit completion and reviewer attribution cannot be overwritten.');
                }
            }
        });

        self::deleting(static function (): never {
            throw new LogicException('MCP execution audit history cannot be deleted through the application.');
        });
    }

    public function aiPrincipal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_principal_id');
    }

    public function humanInitiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_initiator_id');
    }

    public function humanApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_approver_id');
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(McpApproval::class, 'approval_id');
    }
}
