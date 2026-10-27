<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string|null $transaction_type_code
 * @property string|null $amount_decimal
 * @property string|null $currency_code
 */
class Transaction extends Model implements Commentable
{
    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use HasTasks;
    use LogsActivity;
    use SoftDeletes;

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @var array<int, string> */
    protected $fillable = ['financial_account_id', 'vendor_invoice_id', 'transaction_type', 'amount', 'amount_decimal', 'currency_code', 'transaction_type_code', 'payment_method', 'timestamp', 'customer_invoice_id', 'tax_id'];

    protected function casts(): array
    {
        return ['amount_decimal' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $transaction): void {
            if ($transaction->transaction_type_code !== null && ! in_array($transaction->transaction_type_code, ['money_in', 'money_out'], true)) {
                throw ValidationException::withMessages(['transaction_type_code' => 'Direction must be money_in or money_out. Transfer, refund, and reversal workflows require their own linked-event contract.']);
            }
            if ((! $transaction->exists || $transaction->isDirty(['amount_decimal', 'currency_code'])) && ($transaction->amount_decimal !== null || $transaction->currency_code !== null)) {
                if ($transaction->amount_decimal === null || $transaction->currency_code === null) {
                    throw ValidationException::withMessages(['amount_decimal' => 'Canonical decimal amount and currency must be provided together.']);
                }
                if ((float) $transaction->amount_decimal < 0) {
                    throw ValidationException::withMessages(['amount_decimal' => 'Canonical cash-movement amount must be non-negative; direction is stored separately.']);
                }
                if (preg_match('/\A[A-Z]{3}\z/', (string) $transaction->currency_code) !== 1) {
                    throw ValidationException::withMessages(['currency_code' => 'Currency must be an uppercase three-letter code.']);
                }
            }
        });
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['financial_account_id', 'vendor_invoice_id', 'transaction_type', 'amount', 'amount_decimal', 'currency_code', 'transaction_type_code', 'payment_method', 'timestamp', 'customer_invoice_id', 'tax_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function customerInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
