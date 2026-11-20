<?php

namespace App\Models\Mcp;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property array<string, mixed> $arguments
 * @property array<string, mixed> $preview
 * @property array<string, mixed>|null $result
 * @property Carbon|null $expires_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $executed_at
 */
final class McpApproval extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_EXECUTION_BLOCKED = 'execution_blocked';

    public const STATUS_EXECUTION_UNCERTAIN = 'execution_uncertain';

    protected $fillable = [
        'ai_principal_id',
        'human_initiator_id',
        'human_approver_id',
        'oauth_client_id',
        'tool_name',
        'tool_version',
        'target_type',
        'target_id',
        'request_id',
        'idempotency_key',
        'payload_hash',
        'arguments',
        'preview',
        'status',
        'decision_reason',
        'cancellation_reason',
        'result',
        'result_code',
        'error_code',
        'execution_audit_id',
        'expires_at',
        'reviewed_at',
        'cancelled_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'encrypted:array',
            'preview' => 'encrypted:array',
            'result' => 'encrypted:array',
            'expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (self $approval): void {
            $originalStatus = $approval->getRawOriginal('status');
            $currentStatus = $approval->getAttributes()['status'] ?? null;
            $allowedTransitions = [
                self::STATUS_PENDING => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
                self::STATUS_APPROVED => [self::STATUS_EXECUTING, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
                self::STATUS_EXECUTING => [self::STATUS_EXECUTED, self::STATUS_EXECUTION_BLOCKED, self::STATUS_EXECUTION_UNCERTAIN],
            ];
            if ($originalStatus !== $currentStatus
                && ! in_array($currentStatus, $allowedTransitions[$originalStatus] ?? [], true)) {
                throw new LogicException('The MCP approval lifecycle transition is not permitted.');
            }

            foreach ([
                'ai_principal_id',
                'human_initiator_id',
                'oauth_client_id',
                'tool_name',
                'tool_version',
                'target_type',
                'target_id',
                'request_id',
                'idempotency_key',
                'payload_hash',
                'arguments',
                'preview',
                'expires_at',
                'created_at',
            ] as $attribute) {
                self::assertUnchanged($approval, $attribute, 'MCP approval payload and identity fields are immutable.');
            }

            foreach (['human_approver_id', 'decision_reason', 'cancellation_reason', 'reviewed_at', 'cancelled_at', 'execution_audit_id', 'result', 'result_code', 'error_code', 'executed_at'] as $attribute) {
                self::assertWriteOnce($approval, $attribute, 'MCP approval decision and execution fields cannot be overwritten.');
            }
        });

        self::deleting(static function (): never {
            throw new LogicException('MCP approval history cannot be deleted through the application.');
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

    public function executionAudit(): BelongsTo
    {
        return $this->belongsTo(McpExecutionAudit::class, 'execution_audit_id');
    }

    public function getPreviewSummaryAttribute(): string
    {
        $preview = $this->preview ?? [];

        return collect($preview)
            ->map(static function (mixed $value, string|int $key): string {
                $display = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) ($value ?? 'Not specified');

                return $key.': '.mb_substr($display, 0, 120);
            })
            ->implode(' · ');
    }

    private static function assertUnchanged(self $model, string $attribute, string $message): void
    {
        if (($model->getRawOriginal($attribute)) !== ($model->getAttributes()[$attribute] ?? null)) {
            throw new LogicException($message);
        }
    }

    private static function assertWriteOnce(self $model, string $attribute, string $message): void
    {
        $original = $model->getRawOriginal($attribute);
        $current = $model->getAttributes()[$attribute] ?? null;

        if ($original !== null && $original !== $current) {
            throw new LogicException($message);
        }
    }
}
