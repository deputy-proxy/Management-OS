<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

class IssuedInvoice extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CORRECTED = 'corrected';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPE_DEBIT_NOTE = 'debit_note';

    protected $fillable = [
        'public_id', 'legal_entity_id', 'legacy_customer_invoice_id', 'document_type',
        'series', 'invoice_number', 'issue_date', 'supply_date', 'due_date', 'currency_code',
        'net_total', 'tax_total', 'gross_total', 'payment_terms_code', 'payment_status',
        'document_status', 'corrects_issued_invoice_id', 'correction_reason_code',
        'correction_reason', 'issued_snapshot_hash', 'created_by', 'approved_by', 'issued_by',
        'approved_at', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'supply_date' => 'date',
            'due_date' => 'date',
            'net_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'gross_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            if (blank($invoice->getAttribute('public_id'))) {
                $invoice->public_id = (string) Str::uuid();
            }
        });

        static::updating(function (self $invoice): void {
            $originalStatus = $invoice->getOriginal('document_status');
            $changed = array_keys($invoice->getDirty());
            $statusOnlyCorrection = $originalStatus === self::STATUS_ISSUED
                && $invoice->getAttribute('document_status') === self::STATUS_CORRECTED
                && array_diff($changed, ['document_status', 'updated_at']) === [];
            $cancellingApprovedSnapshot = $originalStatus === self::STATUS_APPROVED
                && $invoice->getAttribute('document_status') === self::STATUS_CANCELLED
                && array_diff($changed, ['document_status', 'updated_at']) === [];
            $issuingApprovedSnapshot = $originalStatus === self::STATUS_APPROVED
                && $invoice->getAttribute('document_status') === self::STATUS_ISSUED
                && array_diff($changed, [
                    'series', 'invoice_number', 'document_status', 'issued_snapshot_hash', 'issued_by', 'issued_at', 'updated_at',
                ]) === [];
            if (in_array($originalStatus, [self::STATUS_ISSUED, self::STATUS_CORRECTED], true) && ! $statusOnlyCorrection) {
                throw new RuntimeException('Issued invoice snapshots are immutable. Create a linked correction document instead.');
            }
            if ($originalStatus === self::STATUS_APPROVED && ! $issuingApprovedSnapshot && ! $cancellingApprovedSnapshot) {
                throw new RuntimeException('Approved invoice snapshots cannot be edited. Cancel the draft and create a replacement before issue.');
            }
        });

        static::deleting(function (self $invoice): void {
            if (in_array($invoice->document_status, [self::STATUS_ISSUED, self::STATUS_CORRECTED], true)) {
                throw new RuntimeException('Issued invoice history cannot be deleted.');
            }
        });
    }

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /** @return BelongsTo<CustomerInvoice, $this> */
    public function legacyCustomerInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'legacy_customer_invoice_id');
    }

    /** @return BelongsTo<IssuedInvoice, $this> */
    public function correctedInvoice(): BelongsTo
    {
        return $this->belongsTo(IssuedInvoice::class, 'corrects_issued_invoice_id');
    }

    /** @return HasMany<IssuedInvoiceEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(IssuedInvoiceEvent::class);
    }

    /** @return HasMany<IssuedInvoiceParty, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(IssuedInvoiceParty::class);
    }

    /** @return HasMany<IssuedInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(IssuedInvoiceLine::class);
    }

    /** @return HasMany<IssuedInvoiceTaxBreakdown, $this> */
    public function taxBreakdowns(): HasMany
    {
        return $this->hasMany(IssuedInvoiceTaxBreakdown::class);
    }

    /** @return HasMany<IssuedInvoiceArtifact, $this> */
    public function artifacts(): HasMany
    {
        return $this->hasMany(IssuedInvoiceArtifact::class);
    }

    /** @return HasMany<InvoiceSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(InvoiceSubmission::class);
    }
}
