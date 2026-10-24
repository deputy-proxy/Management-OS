<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImportBatch extends Model
{
    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'financial_account_id', 'created_by', 'reviewed_by', 'bank_code', 'source_sha256',
        'parser_version', 'status', 'currency_code', 'statement_start', 'statement_end',
        'transaction_count', 'review_required_count', 'warning_count', 'preview_payload',
        'validation_summary', 'decision_reason', 'reviewed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'preview_payload' => 'array',
            'validation_summary' => 'array',
            'statement_start' => 'date',
            'statement_end' => 'date',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<BankStatementImportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BankStatementImportItem::class, 'batch_id');
    }

    /** @return HasMany<BankStatementImportEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BankStatementImportEvent::class, 'batch_id');
    }
}
