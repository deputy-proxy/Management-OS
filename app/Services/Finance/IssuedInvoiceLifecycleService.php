<?php

namespace App\Services\Finance;

use App\Models\IssuedInvoice;
use App\Models\IssuedInvoiceSequence;
use App\Models\LegalEntity;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

/** Controlled lifecycle for canonical issued invoices. Fiscal and numbering policy is caller-supplied and versioned. */
class IssuedInvoiceLifecycleService
{
    public function __construct(private readonly IssuedInvoiceCalculationService $calculator) {}

    /** @param array<string, mixed> $payload @param array<string, mixed> $policy */
    public function createDraft(User $actor, array $payload, array $policy): IssuedInvoice
    {
        Gate::forUser($actor)->authorize('customer_invoice.create');
        $this->validateNumberingPolicy($policy['numbering'] ?? null);
        $entityId = filter_var($payload['legal_entity_id'] ?? null, FILTER_VALIDATE_INT);
        if (! $entityId) {
            throw ValidationException::withMessages(['legal_entity_id' => 'Choose a registered issuer.']);
        }
        $entity = LegalEntity::query()->whereKey($entityId)->where('is_active', true)->first();
        if (! $entity) {
            throw ValidationException::withMessages(['legal_entity_id' => 'The issuer is missing or inactive.']);
        }
        $calculationPayload = $payload;
        $calculationPayload['parties'] = $payload['parties'] ?? [
            'seller' => $payload['seller'] ?? [],
            'buyer' => $payload['buyer'] ?? [],
        ];
        $calculated = $this->calculator->calculate($calculationPayload, $policy['calculation'] ?? []);
        $this->validateDocumentType($payload['document_type'] ?? null);
        $this->validateCorrectionPayload($payload);

        return DB::transaction(function () use ($actor, $payload, $calculated, $entity): IssuedInvoice {
            $invoice = IssuedInvoice::query()->create([
                'legal_entity_id' => $entity->getKey(),
                'legacy_customer_invoice_id' => $payload['legacy_customer_invoice_id'] ?? null,
                'document_type' => $payload['document_type'],
                'series' => null,
                'invoice_number' => null,
                'issue_date' => $payload['issue_date'] ?? null,
                'supply_date' => $payload['supply_date'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'currency_code' => $calculated['currency_code'],
                'net_total' => $calculated['totals']['net_total'],
                'tax_total' => $calculated['totals']['tax_total'],
                'gross_total' => $calculated['totals']['gross_total'],
                'payment_terms_code' => $payload['payment_terms_code'] ?? null,
                'document_status' => IssuedInvoice::STATUS_DRAFT,
                'corrects_issued_invoice_id' => $payload['corrects_issued_invoice_id'] ?? null,
                'correction_reason_code' => $payload['correction_reason_code'] ?? null,
                'correction_reason' => $payload['correction_reason'] ?? null,
                'created_by' => $actor->getKey(),
            ]);

            $seller = $payload['parties']['seller'] ?? $payload['seller'] ?? [];
            $seller['role'] = 'seller';
            $buyer = $payload['parties']['buyer'] ?? $payload['buyer'] ?? [];
            $buyer['role'] = 'buyer';
            $invoice->parties()->create($this->partyAttributes($seller));
            $invoice->parties()->create($this->partyAttributes($buyer));

            foreach ($calculated['lines'] as $line) {
                $taxes = $line['taxes'];
                unset($line['taxes']);
                $savedLine = $invoice->lines()->create($line);
                foreach ($taxes as $tax) {
                    $savedLine->taxes()->create($tax);
                }
            }
            foreach ($calculated['tax_breakdowns'] as $tax) {
                $invoice->taxBreakdowns()->create($tax);
            }

            $this->event($invoice, 'draft_created', null, IssuedInvoice::STATUS_DRAFT, $actor, null, [
                'calculation_policy_version' => $calculated['policy_version'],
            ]);

            return $invoice->fresh(['parties', 'lines.taxes', 'taxBreakdowns', 'events']);
        });
    }

    public function approve(int $invoiceId, User $actor): IssuedInvoice
    {
        Gate::forUser($actor)->authorize('customer_invoice.update');

        return DB::transaction(function () use ($invoiceId, $actor): IssuedInvoice {
            $invoice = IssuedInvoice::query()->lockForUpdate()->with(['legalEntity', 'parties', 'lines.taxes', 'taxBreakdowns'])->findOrFail($invoiceId);
            if ($invoice->document_status !== IssuedInvoice::STATUS_DRAFT) {
                throw ValidationException::withMessages(['document_status' => 'Only drafts can be approved.']);
            }
            if (! $invoice->legalEntity?->is_active || $invoice->parties->count() !== 2
                || ! $invoice->parties->contains('role', 'seller') || ! $invoice->parties->contains('role', 'buyer')
                || $invoice->lines->isEmpty() || $invoice->taxBreakdowns->isEmpty()
                || $invoice->currency_code === null || $invoice->net_total === null
                || $invoice->tax_total === null || $invoice->gross_total === null) {
                throw ValidationException::withMessages(['invoice' => 'Issuer, seller/buyer snapshots, currency, totals, lines and tax breakdowns must be complete before approval.']);
            }
            $this->assertTotalsReconcile($invoice);
            $from = $invoice->document_status;
            $invoice->forceFill(['document_status' => IssuedInvoice::STATUS_APPROVED, 'approved_by' => $actor->getKey(), 'approved_at' => now()])->save();
            $this->event($invoice, 'approved', $from, IssuedInvoice::STATUS_APPROVED, $actor);

            activity()->performedOn($invoice)->causedBy($actor)->event('issued_invoice_approved')->withProperties([
                'public_id' => $invoice->public_id, 'gross_total' => $invoice->gross_total, 'currency_code' => $invoice->currency_code,
            ])->log('Issued invoice draft approved');

            return $invoice->fresh(['parties', 'lines.taxes', 'taxBreakdowns', 'events']);
        });
    }

    /** @param array<string, mixed> $numberingPolicy */
    public function issue(int $invoiceId, User $actor, array $numberingPolicy): IssuedInvoice
    {
        Gate::forUser($actor)->authorize('customer_invoice.update');
        $this->validateNumberingPolicy($numberingPolicy);

        return DB::transaction(function () use ($invoiceId, $actor, $numberingPolicy): IssuedInvoice {
            $invoice = IssuedInvoice::query()->lockForUpdate()->with(['parties', 'lines.taxes', 'taxBreakdowns', 'events'])->findOrFail($invoiceId);
            if ($invoice->document_status !== IssuedInvoice::STATUS_APPROVED) {
                throw ValidationException::withMessages(['document_status' => 'Only approved invoices can be issued.']);
            }
            if ($invoice->issue_date === null || $invoice->currency_code === null) {
                throw ValidationException::withMessages(['invoice' => 'Issue date and approved currency are required before numbering.']);
            }
            $entity = LegalEntity::query()->whereKey($invoice->legal_entity_id)->lockForUpdate()->firstOrFail();
            if (! $entity->is_active) {
                throw ValidationException::withMessages(['legal_entity_id' => 'Inactive issuers cannot issue invoices.']);
            }

            $series = trim((string) $numberingPolicy['series']);
            $sequence = IssuedInvoiceSequence::query()->firstOrCreate(
                ['legal_entity_id' => $entity->getKey(), 'series' => $series],
                ['policy_version' => $numberingPolicy['version'], 'next_number' => $numberingPolicy['start_at']],
            );
            $sequence = IssuedInvoiceSequence::query()->whereKey($sequence->getKey())->lockForUpdate()->firstOrFail();
            if ($sequence->policy_version !== $numberingPolicy['version']) {
                throw ValidationException::withMessages(['numbering_policy.version' => 'The sequence is bound to a different numbering policy version; reconcile and explicitly migrate it before issuing.']);
            }
            $number = (string) $sequence->next_number;
            $padding = (int) $numberingPolicy['padding'];
            if ($padding > 0) {
                $number = str_pad($number, $padding, '0', STR_PAD_LEFT);
            }
            $prefix = (string) ($numberingPolicy['prefix'] ?? '');
            $legalNumber = $prefix.$number;
            if (strlen($legalNumber) > 64) {
                throw ValidationException::withMessages(['numbering_policy' => 'The formatted legal number exceeds the supported length.']);
            }

            $snapshot = $this->snapshot($invoice->forceFill(['series' => $series, 'invoice_number' => $legalNumber]), $numberingPolicy['version']);
            $hash = hash('sha256', $this->canonicalJson($snapshot));
            $from = $invoice->document_status;
            $invoice->forceFill([
                'series' => $series,
                'invoice_number' => $legalNumber,
                'document_status' => IssuedInvoice::STATUS_ISSUED,
                'issued_snapshot_hash' => $hash,
                'issued_by' => $actor->getKey(),
                'issued_at' => now(),
            ])->save();
            $sequence->forceFill(['next_number' => $sequence->next_number + 1])->save();
            $this->event($invoice, 'issued', $from, IssuedInvoice::STATUS_ISSUED, $actor, null, [
                'series' => $series, 'invoice_number' => $legalNumber,
                'snapshot_sha256' => $hash, 'numbering_policy_version' => $numberingPolicy['version'],
            ]);
            if ($invoice->corrects_issued_invoice_id !== null) {
                $original = IssuedInvoice::query()->lockForUpdate()->findOrFail($invoice->corrects_issued_invoice_id);
                if ($original->document_status !== IssuedInvoice::STATUS_ISSUED || $original->legal_entity_id !== $invoice->legal_entity_id) {
                    throw ValidationException::withMessages(['corrects_issued_invoice_id' => 'The original must still be issued by the same legal entity when the correction is issued.']);
                }
                $original->forceFill(['document_status' => IssuedInvoice::STATUS_CORRECTED])->save();
                $this->event($original, 'corrected_by_new_document', IssuedInvoice::STATUS_ISSUED, IssuedInvoice::STATUS_CORRECTED, $actor, null, [
                    'correction_public_id' => $invoice->public_id, 'correction_snapshot_sha256' => $hash,
                ]);
            }
            activity()->performedOn($invoice)->causedBy($actor)->event('issued_invoice_issued')->withProperties([
                'public_id' => $invoice->public_id, 'series' => $series, 'invoice_number' => $legalNumber,
                'snapshot_sha256' => $hash, 'currency_code' => $invoice->currency_code,
            ])->log('Issued invoice issued');

            return $invoice->fresh(['parties', 'lines.taxes', 'taxBreakdowns', 'events']);
        }, attempts: 3);
    }

    public function cancelDraft(int $invoiceId, User $actor, string $reason): IssuedInvoice
    {
        Gate::forUser($actor)->authorize('customer_invoice.update');
        $this->validateReason($reason);

        return DB::transaction(function () use ($invoiceId, $actor, $reason): IssuedInvoice {
            $invoice = IssuedInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if (! in_array($invoice->document_status, [IssuedInvoice::STATUS_DRAFT, IssuedInvoice::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages(['document_status' => 'Issued documents cannot be cancelled or edited. Create a reviewed correction document instead.']);
            }
            $from = $invoice->document_status;
            $invoice->forceFill(['document_status' => IssuedInvoice::STATUS_CANCELLED])->save();
            $this->event($invoice, 'draft_cancelled', $from, IssuedInvoice::STATUS_CANCELLED, $actor, trim($reason));
            activity()->performedOn($invoice)->causedBy($actor)->event('issued_invoice_draft_cancelled')->withProperties([
                'public_id' => $invoice->public_id, 'from_status' => $from, 'reason' => trim($reason),
            ])->log('Issued invoice draft cancelled');

            return $invoice->fresh(['events']);
        });
    }

    public function createCorrection(int $originalInvoiceId, User $actor, array $payload, array $policy): IssuedInvoice
    {
        Gate::forUser($actor)->authorize('customer_invoice.create');
        $original = IssuedInvoice::query()->whereKey($originalInvoiceId)->firstOrFail();
        if ($original->document_status !== IssuedInvoice::STATUS_ISSUED || blank($original->issued_snapshot_hash)) {
            throw ValidationException::withMessages(['corrects_issued_invoice_id' => 'Corrections must reference an issued invoice with a retained snapshot hash.']);
        }
        if (! in_array($payload['document_type'] ?? null, [IssuedInvoice::TYPE_CREDIT_NOTE, IssuedInvoice::TYPE_DEBIT_NOTE], true)) {
            throw ValidationException::withMessages(['document_type' => 'A correction must use an explicitly supported credit-note or debit-note type.']);
        }
        if (($payload['legal_entity_id'] ?? null) != $original->legal_entity_id) {
            throw ValidationException::withMessages(['legal_entity_id' => 'A correction must use the original issuer.']);
        }
        $payload['corrects_issued_invoice_id'] = $original->getKey();
        if (blank($payload['correction_reason'] ?? null)) {
            throw ValidationException::withMessages(['correction_reason' => 'A correction reason is required.']);
        }

        return $this->createDraft($actor, $payload, $policy);
    }

    private function validateNumberingPolicy(mixed $policy): void
    {
        if (! is_array($policy) || ! is_string($policy['version'] ?? null) || trim($policy['version']) === ''
            || ! is_string($policy['series'] ?? null) || trim($policy['series']) === ''
            || ! is_int($policy['start_at'] ?? null) || $policy['start_at'] < 1
            || ! is_int($policy['padding'] ?? null) || $policy['padding'] < 0 || $policy['padding'] > 32
            || (isset($policy['prefix']) && ! is_string($policy['prefix']))) {
            throw ValidationException::withMessages(['numbering_policy' => 'A versioned numbering policy with explicit series, positive starting number, prefix and padding is required.']);
        }
    }

    private function validateDocumentType(mixed $type): void
    {
        if (! in_array($type, [IssuedInvoice::TYPE_INVOICE, IssuedInvoice::TYPE_CREDIT_NOTE, IssuedInvoice::TYPE_DEBIT_NOTE], true)) {
            throw ValidationException::withMessages(['document_type' => 'Choose an explicitly supported invoice, credit-note or debit-note type.']);
        }
    }

    /** @param array<string, mixed> $payload */
    private function validateCorrectionPayload(array $payload): void
    {
        $correction = in_array($payload['document_type'] ?? null, [IssuedInvoice::TYPE_CREDIT_NOTE, IssuedInvoice::TYPE_DEBIT_NOTE], true);
        if ($correction && (empty($payload['corrects_issued_invoice_id']) || blank($payload['correction_reason'] ?? null))) {
            throw ValidationException::withMessages(['corrects_issued_invoice_id' => 'Correction documents require an original invoice and a reason.']);
        }
        if (! $correction && ! empty($payload['corrects_issued_invoice_id'])) {
            throw ValidationException::withMessages(['corrects_issued_invoice_id' => 'Only credit/debit notes may reference an original invoice.']);
        }
    }

    private function validateReason(string $reason): void
    {
        if (trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'A reason of 1 to 1000 characters is required.']);
        }
    }

    /** @param array<string, mixed> $party @return array<string, mixed> */
    private function partyAttributes(array $party): array
    {
        $allowed = ['role', 'legal_name', 'country_code', 'address_line_1', 'address_line_2', 'locality', 'postal_code', 'region', 'tax_identifier_type', 'tax_identifier', 'vat_registration_id', 'registration_id', 'email', 'endpoint_id', 'endpoint_scheme_id'];

        return array_intersect_key($party, array_flip($allowed));
    }

    private function assertTotalsReconcile(IssuedInvoice $invoice): void
    {
        $net = BigDecimal::of((string) $invoice->net_total);
        $tax = BigDecimal::of((string) $invoice->tax_total);
        $gross = BigDecimal::of((string) $invoice->gross_total);
        $lineNet = BigDecimal::zero();
        $lineTax = BigDecimal::zero();
        foreach ($invoice->lines as $line) {
            $lineNet = $lineNet->plus(BigDecimal::of((string) $line->net_amount));
            foreach ($line->taxes as $lineTaxRow) {
                $lineTax = $lineTax->plus(BigDecimal::of((string) $lineTaxRow->tax_amount));
            }
        }
        $breakdownTax = BigDecimal::zero();
        foreach ($invoice->taxBreakdowns as $breakdown) {
            $breakdownTax = $breakdownTax->plus(BigDecimal::of((string) $breakdown->tax_amount));
        }
        if ($net->plus($tax)->compareTo($gross) !== 0) {
            throw ValidationException::withMessages(['gross_total' => 'Net plus tax must equal the gross total before approval.']);
        }
        if ($lineNet->compareTo($net) !== 0) {
            throw ValidationException::withMessages(['net_total' => 'Header net total must equal the sum of invoice line net amounts.']);
        }
        if ($lineTax->compareTo($tax) !== 0 || $breakdownTax->compareTo($tax) !== 0) {
            throw ValidationException::withMessages(['tax_total' => 'Header tax total must reconcile with both line taxes and document tax breakdowns.']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(IssuedInvoice $invoice, string $numberingPolicyVersion): array
    {
        $draftEvent = $invoice->events->firstWhere('event_type', 'draft_created');
        $draftMetadata = json_decode((string) $draftEvent?->getRawOriginal('safe_metadata'), true);

        return [
            'snapshot_version' => '1', 'numbering_policy_version' => $numberingPolicyVersion,
            'calculation_policy_version' => is_array($draftMetadata) ? ($draftMetadata['calculation_policy_version'] ?? null) : null,
            'public_id' => $invoice->public_id, 'legal_entity_id' => $invoice->legal_entity_id,
            'document_type' => $invoice->document_type, 'series' => $invoice->series,
            'invoice_number' => $invoice->invoice_number, 'issue_date' => (string) $invoice->issue_date,
            'supply_date' => $invoice->supply_date ? (string) $invoice->supply_date : null,
            'due_date' => $invoice->due_date ? (string) $invoice->due_date : null,
            'currency_code' => $invoice->currency_code, 'net_total' => $invoice->net_total,
            'tax_total' => $invoice->tax_total, 'gross_total' => $invoice->gross_total,
            'corrects_issued_invoice_id' => $invoice->corrects_issued_invoice_id,
            'correction_reason_code' => $invoice->correction_reason_code, 'correction_reason' => $invoice->correction_reason,
            'parties' => $invoice->parties->sortBy('role')->values()->map(fn ($party): array => $party->only([
                'role', 'legal_name', 'country_code', 'address_line_1', 'address_line_2', 'locality', 'postal_code',
                'region', 'tax_identifier_type', 'tax_identifier', 'vat_registration_id', 'registration_id', 'email',
            ]) + ((filled($party->endpoint_id) || filled($party->endpoint_scheme_id)) ? $party->only(['endpoint_id', 'endpoint_scheme_id']) : []))->all(),
            'lines' => $invoice->lines->sortBy('line_number')->values()->map(fn ($line): array => [
                ...$line->only(['line_number', 'description', 'seller_item_code', 'quantity', 'unit_code', 'unit_price_net', 'discount_amount', 'net_amount']),
                'taxes' => $line->taxes->sortBy(['tax_scheme_code', 'tax_category_code'])->values()->map(fn ($tax): array => $tax->only([
                    'tax_scheme_code', 'tax_category_code', 'tax_rate_percent', 'taxable_base', 'tax_amount',
                    'exemption_reason_code', 'exemption_reason', 'reverse_charge',
                ]))->all(),
            ])->all(),
            'tax_breakdowns' => $invoice->taxBreakdowns->sortBy(['tax_scheme_code', 'tax_category_code'])->values()->map(fn ($tax): array => $tax->only([
                'tax_scheme_code', 'tax_category_code', 'tax_rate_percent', 'taxable_base', 'tax_amount', 'exemption_reason_code', 'exemption_reason',
            ]))->all(),
        ];
    }

    /** @throws JsonException */
    private function canonicalJson(array $snapshot): string
    {
        return json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** @param array<string, mixed>|null $metadata */
    private function event(IssuedInvoice $invoice, string $type, ?string $from, ?string $to, User $actor, ?string $reason = null, ?array $metadata = null): void
    {
        $invoice->events()->create([
            'event_type' => $type, 'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actor->getKey(), 'reason' => $reason,
            'correlation_uuid' => (string) Str::uuid(), 'safe_metadata' => $metadata,
        ]);
    }
}
