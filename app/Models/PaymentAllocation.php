<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PaymentAllocation extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_REJECTED = 'rejected';

    public const KIND_INVOICE = 'invoice';

    public const KIND_FEE = 'fee';

    protected $fillable = [
        'transaction_id', 'customer_invoice_id', 'vendor_invoice_id', 'allocation_kind',
        'amount_decimal', 'currency_code', 'status', 'reason', 'created_by', 'confirmed_by',
        'confirmed_at', 'reversed_by', 'reversed_at', 'reversal_reason', 'rejected_by', 'rejected_at', 'rejection_reason',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $allocation): void {
            if (! in_array($allocation->allocation_kind, [self::KIND_INVOICE, self::KIND_FEE], true)) {
                throw ValidationException::withMessages(['allocation_kind' => 'Unsupported allocation type.']);
            }
            if (! in_array($allocation->status, [self::STATUS_PROPOSED, self::STATUS_CONFIRMED, self::STATUS_REVERSED, self::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages(['status' => 'Unsupported allocation state.']);
            }
            try {
                $amount = BigDecimal::of((string) $allocation->amount_decimal);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['amount_decimal' => 'Allocation amount must be a valid decimal.']);
            }
            if ($amount->compareTo(BigDecimal::zero()) <= 0) {
                throw ValidationException::withMessages(['amount_decimal' => 'Allocation amount must be greater than zero.']);
            }
            if (preg_match('/^[A-Z]{3}$/', (string) $allocation->currency_code) !== 1) {
                throw ValidationException::withMessages(['currency_code' => 'Currency must be an uppercase three-letter code.']);
            }
            $hasCustomerInvoice = $allocation->customer_invoice_id !== null;
            $hasVendorInvoice = $allocation->vendor_invoice_id !== null;
            if (($allocation->allocation_kind === self::KIND_INVOICE && $hasCustomerInvoice === $hasVendorInvoice)
                || ($allocation->allocation_kind === self::KIND_FEE && ($hasCustomerInvoice || $hasVendorInvoice))) {
                throw ValidationException::withMessages(['target_id' => 'Invoice allocations require exactly one invoice target; fees cannot have an invoice target.']);
            }
            if (trim((string) $allocation->reason) === '') {
                throw ValidationException::withMessages(['reason' => 'An allocation reason is required.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['amount_decimal' => 'decimal:2', 'confirmed_at' => 'datetime', 'reversed_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customerInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class);
    }

    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([
            'transaction_id', 'customer_invoice_id', 'vendor_invoice_id', 'allocation_kind',
            'amount_decimal', 'currency_code', 'status', 'reason', 'confirmed_by', 'confirmed_at',
            'reversed_by', 'reversed_at', 'reversal_reason', 'rejected_by', 'rejected_at', 'rejection_reason',
        ])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
