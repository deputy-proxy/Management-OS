<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property array<string, string> $dimensions
 */
class BudgetVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'budget_id', 'version', 'period_start', 'period_end', 'currency_code', 'dimensions', 'status',
        'change_summary', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
        'retired_by', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'dimensions' => 'array',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if (! preg_match('/^[A-Z]{3}$/', (string) $version->currency_code)) {
                throw ValidationException::withMessages(['currency_code' => 'Currency must be a three-letter uppercase ISO currency code.']);
            }
            if (Carbon::parse($version->period_end)->lt(Carbon::parse($version->period_start))) {
                throw ValidationException::withMessages(['period_end' => 'Budget period end must be on or after its start.']);
            }
        });

        static::creating(function (self $version): void {
            if ($version->status !== self::STATUS_DRAFT) {
                throw new LogicException('Budget versions must be created as drafts.');
            }
        });

        static::updating(function (self $version): void {
            $originalStatus = $version->getOriginal('status');
            $persistedStatus = static::query()->whereKey($version->getKey())->value('status');
            if ((string) $persistedStatus !== (string) $originalStatus) {
                throw new LogicException('Budget version state changed. Refresh the record before editing it.');
            }
            if ($originalStatus === self::STATUS_DRAFT) {
                return;
            }
            $allowed = match ($originalStatus) {
                self::STATUS_SUBMITTED => ['status', 'approved_by', 'approved_at', 'updated_at'],
                self::STATUS_APPROVED => ['status', 'retired_by', 'retired_at', 'updated_at'],
                default => [],
            };
            if (array_diff(array_keys($version->getDirty()), $allowed) !== []) {
                throw new LogicException('Submitted, approved, and retired budget versions are immutable. Create a new version instead.');
            }
            if ($originalStatus === self::STATUS_SUBMITTED && ($version->status !== self::STATUS_APPROVED || ! $version->approved_at || ! $version->approved_by)) {
                throw new LogicException('A submitted budget version can only transition to approved through the approval workflow.');
            }
            if ($originalStatus === self::STATUS_APPROVED && ($version->status !== self::STATUS_RETIRED || ! $version->retired_at || ! $version->retired_by)) {
                throw new LogicException('An approved budget version can only transition to retired through the retirement workflow.');
            }
            if ($originalStatus === self::STATUS_RETIRED) {
                throw new LogicException('Retired budget versions are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Budget versions are retained as historical audit records and cannot be deleted.'));
    }

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** @return HasMany<BudgetLineItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(BudgetLineItem::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<BudgetVersionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BudgetVersionEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function retirer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }
}