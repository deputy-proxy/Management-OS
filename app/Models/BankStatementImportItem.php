<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementImportItem extends Model
{
    protected $fillable = [
        'batch_id', 'financial_account_id', 'line_fingerprint', 'transaction_date', 'direction',
        'amount_decimal', 'currency_code', 'transaction_type_code', 'classification_status', 'description',
        'operation_reference', 'document_reference', 'source_sha256', 'parser_version', 'source_page',
        'source_row', 'review_reasons',
    ];

    protected function casts(): array
    {
        return ['amount_decimal' => 'decimal:2', 'transaction_date' => 'date', 'review_reasons' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BankStatementImportBatch::class, 'batch_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }
}
