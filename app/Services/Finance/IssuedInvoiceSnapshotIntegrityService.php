<?php

namespace App\Services\Finance;

use App\Models\IssuedInvoice;
use Illuminate\Validation\ValidationException;

class IssuedInvoiceSnapshotIntegrityService
{
    /** @return array<string, mixed> */
    public function assertValid(IssuedInvoice $invoice): array
    {
        if (blank($invoice->issued_snapshot_hash)) {
            throw ValidationException::withMessages(['invoice' => 'Invoice has no issued snapshot hash.']);
        }
        $draft = $invoice->events->firstWhere('event_type', 'draft_created');
        $draftMeta = json_decode((string) $draft?->getRawOriginal('safe_metadata'), true);
        $issued = $invoice->events->firstWhere('event_type', 'issued');
        $issuedMeta = json_decode((string) $issued?->getRawOriginal('safe_metadata'), true);
        $numberingVersion = is_array($issuedMeta) ? ($issuedMeta['numbering_policy_version'] ?? null) : null;
        if (! is_string($numberingVersion) || $numberingVersion === '') {
            throw ValidationException::withMessages(['invoice' => 'Issuance event has no numbering policy version.']);
        }
        $snapshot = [
            'snapshot_version' => '1', 'numbering_policy_version' => $numberingVersion,
            'calculation_policy_version' => is_array($draftMeta) ? ($draftMeta['calculation_policy_version'] ?? null) : null,
            'public_id' => $invoice->public_id, 'legal_entity_id' => $invoice->legal_entity_id,
            'document_type' => $invoice->document_type, 'series' => $invoice->series, 'invoice_number' => $invoice->invoice_number,
            'issue_date' => (string) $invoice->issue_date, 'supply_date' => $invoice->supply_date ? (string) $invoice->supply_date : null,
            'due_date' => $invoice->due_date ? (string) $invoice->due_date : null, 'currency_code' => $invoice->currency_code,
            'net_total' => $invoice->net_total, 'tax_total' => $invoice->tax_total, 'gross_total' => $invoice->gross_total,
            'corrects_issued_invoice_id' => $invoice->corrects_issued_invoice_id,
            'correction_reason_code' => $invoice->correction_reason_code, 'correction_reason' => $invoice->correction_reason,
            'parties' => $invoice->parties->sortBy('role')->values()->map(fn ($party): array => $party->only([
                'role', 'legal_name', 'country_code', 'address_line_1', 'address_line_2', 'locality', 'postal_code', 'region',
                'tax_identifier_type', 'tax_identifier', 'vat_registration_id', 'registration_id', 'email',
            ]) + ((filled($party->endpoint_id) || filled($party->endpoint_scheme_id)) ? $party->only(['endpoint_id', 'endpoint_scheme_id']) : []))->all(),
            'lines' => $invoice->lines->sortBy('line_number')->values()->map(fn ($line): array => [
                ...$line->only(['line_number', 'description', 'seller_item_code', 'quantity', 'unit_code', 'unit_price_net', 'discount_amount', 'net_amount']),
                'taxes' => $line->taxes->sortBy(['tax_scheme_code', 'tax_category_code'])->values()->map(fn ($tax): array => $tax->only([
                    'tax_scheme_code', 'tax_category_code', 'tax_rate_percent', 'taxable_base', 'tax_amount', 'exemption_reason_code', 'exemption_reason', 'reverse_charge',
                ]))->all(),
            ])->all(),
            'tax_breakdowns' => $invoice->taxBreakdowns->sortBy(['tax_scheme_code', 'tax_category_code'])->values()->map(fn ($tax): array => $tax->only([
                'tax_scheme_code', 'tax_category_code', 'tax_rate_percent', 'taxable_base', 'tax_amount', 'exemption_reason_code', 'exemption_reason',
            ]))->all(),
        ];
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if (! hash_equals((string) $invoice->issued_snapshot_hash, hash('sha256', $json))) {
            throw ValidationException::withMessages(['invoice' => 'Stored invoice data differs from the immutable issued snapshot.']);
        }

        return $snapshot;
    }
}
