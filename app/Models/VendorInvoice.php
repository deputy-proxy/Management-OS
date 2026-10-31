<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasProductResources;
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

class VendorInvoice extends Model implements Commentable
{
    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use HasProductResources;
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
    protected $fillable = ['vendor_id', 'vendor_contract_id', 'worker_engagement_id', 'amount_without_vat', 'vat', 'currency', 'amount_decimal', 'amount_without_vat_decimal', 'vat_decimal', 'currency_code', 'status_code', 'month', 'status', 'invoice', 'amount', 'invoice_number', 'identifier'];

    protected function casts(): array
    {
        return ['month' => 'date', 'payable_on' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'amount_decimal' => 'decimal:2', 'amount_without_vat_decimal' => 'decimal:2', 'vat_decimal' => 'decimal:2'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorContract(): BelongsTo
    {
        return $this->belongsTo(VendorContract::class);
    }

    public function workerEngagement(): BelongsTo
    {
        return $this->belongsTo(WorkerEngagement::class);
    }

    public function correctingInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_vendor_invoice_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_vendor_invoice_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(VendorInvoiceEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            if ($invoice->exists && $invoice->getOriginal('workflow_status') !== null
                && in_array($invoice->getOriginal('workflow_status'), ['approved', 'payable', 'partially_paid', 'paid', 'corrected', 'reversed'], true)) {
                $protected = ['vendor_id', 'vendor_contract_id', 'worker_engagement_id', 'amount', 'amount_without_vat', 'vat', 'currency', 'currency_code', 'amount_decimal', 'amount_without_vat_decimal', 'vat_decimal', 'invoice', 'invoice_number', 'identifier', 'month'];
                if ($invoice->isDirty($protected)) {
                    throw ValidationException::withMessages(['workflow_status' => 'Reviewed contractor invoice details are locked. Record a linked correction instead.']);
                }
            }
            if ($invoice->worker_engagement_id) {
                $engagement = WorkerEngagement::query()->find($invoice->worker_engagement_id);
                if (! $engagement || $engagement->engagement_type !== 'pfa_contractor' || (int) $engagement->vendor_id !== (int) $invoice->vendor_id) {
                    throw ValidationException::withMessages(['worker_engagement_id' => 'The invoice must link to a PFA contractor engagement belonging to the selected vendor.']);
                }
                if ($invoice->vendor_contract_id && (int) $engagement->vendor_contract_id !== (int) $invoice->vendor_contract_id) {
                    throw ValidationException::withMessages(['vendor_contract_id' => 'The invoice contract must match the linked contractor engagement.']);
                }
            }
            if (! in_array($invoice->getAttribute('tax_treatment_status') ?? 'unsupported', ['unsupported', 'configured'], true)) {
                throw ValidationException::withMessages(['tax_treatment_status' => 'Choose a supported tax-treatment state.']);
            }
            if ($invoice->getAttribute('tax_treatment_status') === 'configured'
                && (blank($invoice->getAttribute('tax_treatment_rule_key')) || blank($invoice->getAttribute('tax_treatment_rule_version')))) {
                throw ValidationException::withMessages(['tax_treatment_rule_key' => 'Configured tax treatment requires an explicit rule key and version.']);
            }
        });

        static::deleting(function (self $invoice): void {
            if ($invoice->lifecycleEvents()->exists() || $invoice->paymentAllocations()->exists() || $invoice->corrections()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Invoices with lifecycle, payment, or correction history cannot be deleted.']);
            }
            if (in_array($invoice->workflow_status, ['approved', 'payable', 'partially_paid', 'paid', 'corrected', 'reversed'], true)) {
                throw ValidationException::withMessages(['invoice' => 'Reviewed contractor invoices cannot be deleted.']);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['vendor_id', 'vendor_contract_id', 'worker_engagement_id', 'workflow_status', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_reason', 'payable_on', 'corrects_vendor_invoice_id', 'correction_reason', 'reversal_reason', 'tax_treatment_status', 'tax_treatment_rule_key', 'tax_treatment_rule_version', 'amount_without_vat', 'vat', 'currency', 'amount_decimal', 'amount_without_vat_decimal', 'vat_decimal', 'currency_code', 'status_code', 'month', 'status', 'amount', 'invoice_number', 'identifier'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
