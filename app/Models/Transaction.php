<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;
use App\Models\Concerns\HasTasks;

class Transaction extends Model implements Commentable
{
    use HasPanelSearchTitle;

    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;

    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
    /** @var array<int, string> */
    protected $fillable = ['financial_account_id', 'vendor_invoice_id', 'transaction_type', 'amount', 'amount_decimal', 'currency_code', 'payment_method', 'timestamp', 'customer_invoice_id', 'tax_id'];

    protected function casts(): array
    {
        return ['amount_decimal' => 'decimal:2'];
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
        return LogOptions::defaults()->logOnly(['financial_account_id', 'vendor_invoice_id', 'transaction_type', 'amount', 'amount_decimal', 'currency_code', 'payment_method', 'timestamp', 'customer_invoice_id', 'tax_id'])->logOnlyDirty()->dontLogEmptyChanges();
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