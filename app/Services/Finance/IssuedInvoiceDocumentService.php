<?php

namespace App\Services\Finance;

use App\Models\IssuedInvoice;
use App\Models\IssuedInvoiceArtifact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

/** Creates a deterministic human-readable derivative of an immutable issued invoice. */
class IssuedInvoiceDocumentService
{
    public function generate(int $invoiceId, User $actor): IssuedInvoiceArtifact
    {
        Gate::forUser($actor)->authorize('customer_invoice.index');

        return DB::transaction(function () use ($invoiceId, $actor): IssuedInvoiceArtifact {
            $invoice = IssuedInvoice::query()->lockForUpdate()->with([
                'legalEntity', 'parties', 'lines.taxes', 'taxBreakdowns', 'events', 'correctedInvoice',
            ])->findOrFail($invoiceId);

            if (! in_array($invoice->document_status, [IssuedInvoice::STATUS_ISSUED, IssuedInvoice::STATUS_CORRECTED], true)) {
                throw ValidationException::withMessages(['document_status' => 'Only issued or corrected invoices can be rendered as final documents.']);
            }
            if (blank($invoice->issued_snapshot_hash) || blank($invoice->invoice_number) || blank($invoice->series)
                || $invoice->issue_date === null || $invoice->currency_code === null || $invoice->legalEntity === null) {
                throw ValidationException::withMessages(['invoice' => 'The issued invoice is missing required immutable document fields.']);
            }

            if (! hash_equals((string) $invoice->issued_snapshot_hash, hash('sha256', $this->canonicalJson($this->snapshot($invoice))))) {
                throw ValidationException::withMessages(['invoice' => 'Stored invoice data no longer matches the immutable issued snapshot hash. Rendering was refused.']);
            }

            $existing = $invoice->artifacts()->where('artifact_type', 'invoice_html')->latest('id')->first();
            if ($existing !== null) {
                $disk = Storage::disk($existing->storage_disk);
                if (! $disk->exists($existing->storage_key)) {
                    throw ValidationException::withMessages(['artifact' => 'The previously generated invoice document is missing from private storage. Restore it from backup before regenerating.']);
                }
                $bytes = $disk->get($existing->storage_key);
                if (! hash_equals($existing->sha256, hash('sha256', $bytes)) || strlen($bytes) !== (int) $existing->byte_size) {
                    throw ValidationException::withMessages(['artifact' => 'The previously generated invoice document failed its integrity check. Restore the verified artifact from backup.']);
                }

                return $existing;
            }

            $html = $this->render($invoice);
            $sha256 = hash('sha256', $html);
            $key = 'issued-invoices/'.$invoice->public_id.'/'.$invoice->issued_snapshot_hash.'/invoice-'.$sha256.'.html';
            $disk = Storage::disk('local');
            if (! $disk->put($key, $html)) {
                throw new RuntimeException('Unable to persist the generated invoice document to private storage.');
            }

            try {
                return $invoice->artifacts()->create([
                    'artifact_type' => 'invoice_html', 'storage_disk' => 'local', 'storage_key' => $key,
                    'media_type' => 'text/html; charset=UTF-8', 'byte_size' => strlen($html),
                    'sha256' => $sha256, 'created_by' => $actor->getKey(),
                ]);
            } catch (Throwable $exception) {
                $disk->delete($key);
                throw $exception;
            }
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function snapshot(IssuedInvoice $invoice): array
    {
        $draft = $invoice->events->firstWhere('event_type', 'draft_created');
        $draftMeta = json_decode((string) $draft?->getRawOriginal('safe_metadata'), true);
        $issued = $invoice->events->firstWhere('event_type', 'issued');
        $issuedMeta = json_decode((string) $issued?->getRawOriginal('safe_metadata'), true);
        $numberingVersion = is_array($issuedMeta) ? ($issuedMeta['numbering_policy_version'] ?? null) : null;
        if (! is_string($numberingVersion) || $numberingVersion === '') {
            throw ValidationException::withMessages(['invoice' => 'The issuance event does not contain a numbering policy version.']);
        }

        return [
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
    }

    /** @throws JsonException */
    private function canonicalJson(array $snapshot): string
    {
        return json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function render(IssuedInvoice $invoice): string
    {
        $seller = $invoice->parties->firstWhere('role', 'seller');
        $buyer = $invoice->parties->firstWhere('role', 'buyer');
        if ($seller === null || $buyer === null || $invoice->lines->isEmpty() || $invoice->taxBreakdowns->isEmpty()) {
            throw ValidationException::withMessages(['invoice' => 'Seller, buyer, invoice lines and tax breakdowns are required for document generation.']);
        }
        $title = match ($invoice->document_type) {
            IssuedInvoice::TYPE_CREDIT_NOTE => 'Notă de credit',
            IssuedInvoice::TYPE_DEBIT_NOTE => 'Notă de debit',
            default => 'Factură',
        };
        $lines = $invoice->lines->sortBy('line_number')->map(function ($line) use ($invoice): string {
            $taxes = $line->taxes->sortBy(['tax_scheme_code', 'tax_category_code'])->map(function ($tax) use ($invoice): string {
                $rate = $tax->tax_rate_percent === null ? 'Nespecificat' : rtrim(rtrim((string) $tax->tax_rate_percent, '0'), '.').'%';
                $reason = $tax->exemption_reason ? '<div class="muted">'.$this->escape($tax->exemption_reason).'</div>' : '';

                return '<li>'.$this->escape($tax->tax_scheme_code.' / '.$tax->tax_category_code).' · '.$rate.' · bază '.$this->money($tax->taxable_base, $invoice->currency_code).' · taxă '.$this->money($tax->tax_amount, $invoice->currency_code).$reason.'</li>';
            })->implode('');

            return '<tr><td>'.(int) $line->line_number.'</td><td>'.$this->escape($line->description)
                .($line->seller_item_code ? '<div class="muted">Cod: '.$this->escape($line->seller_item_code).'</div>' : '')
                .($taxes !== '' ? '<ul class="taxes">'.$taxes.'</ul>' : '')
                .'</td><td class="num">'.$this->escape((string) $line->quantity).' '.$this->escape($line->unit_code)
                .'</td><td class="num">'.$this->money($line->unit_price_net, $invoice->currency_code)
                .'</td><td class="num">'.$this->money($line->discount_amount, $invoice->currency_code)
                .'</td><td class="num">'.$this->money($line->net_amount, $invoice->currency_code).'</td></tr>';
        })->implode('');
        $taxRows = $invoice->taxBreakdowns->sortBy(['tax_scheme_code', 'tax_category_code'])->map(fn ($tax): string => '<tr><td>'.$this->escape($tax->tax_scheme_code).'</td><td>'.$this->escape($tax->tax_category_code).'</td><td class="num">'
            .$this->escape($tax->tax_rate_percent === null ? 'Nespecificat' : rtrim(rtrim((string) $tax->tax_rate_percent, '0'), '.').'%')
            .'</td><td class="num">'.$this->money($tax->taxable_base, $invoice->currency_code).'</td><td class="num">'.$this->money($tax->tax_amount, $invoice->currency_code).'</td></tr>')->implode('');
        $original = $invoice->correctedInvoice;
        $correction = $original === null ? '' : '<section class="correction"><h2>Document corectat</h2><p>Document original: '
            .$this->escape(trim((string) $original->series.' '.$original->invoice_number)).'</p><p>Referință document: '.$this->escape($original->public_id)
            .'</p><p>Motiv: '.$this->escape((string) $invoice->correction_reason).'</p></section>';
        $paymentTerms = filled($invoice->payment_terms_code) ? '<p>Condiții de plată: '.$this->escape((string) $invoice->payment_terms_code).'</p>' : '';
        $css = 'body{font:14px/1.45 Arial,sans-serif;color:#17202a;margin:16mm}h1{font-size:25px}h2{font-size:15px}p{margin:3px 0}.top,.parties{display:flex;justify-content:space-between;gap:20px;margin-bottom:20px}.panel,.correction{border:1px solid #d5dbe0;padding:12px;flex:1}.muted,.foot{color:#59636e;font-size:11px}table{border-collapse:collapse;width:100%;margin:12px 0 20px}th,td{border-bottom:1px solid #d5dbe0;padding:7px 5px;text-align:left;vertical-align:top}.num{text-align:right;white-space:nowrap}.taxes{padding-left:16px;font-size:11px}.totals{margin-left:auto;width:340px}.totals td{border:0}.grand{font-weight:bold;border-top:2px solid #243746!important}.foot{margin-top:24px;padding-top:8px;border-top:1px solid #d5dbe0;overflow-wrap:anywhere}@media print{.panel,.correction,tr{break-inside:avoid}}';

        return '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$this->escape($title.' '.$invoice->series.' '.$invoice->invoice_number).'</title><style>'.$css.'</style></head><body>'
            .'<header class="top"><div><h1>'.$this->escape($title).'</h1><p>'.$this->escape($seller->legal_name).'</p></div><div><h2>'.$this->escape((string) $invoice->series).' / '.$this->escape((string) $invoice->invoice_number).'</h2>'
            .'<p>Data emiterii: '.$this->escape((string) $invoice->issue_date).'</p><p>Monedă: '.$this->escape((string) $invoice->currency_code).'</p>'
            .($invoice->supply_date ? '<p>Data livrării/prestării: '.$this->escape((string) $invoice->supply_date).'</p>' : '')
            .($invoice->due_date ? '<p>Scadență: '.$this->escape((string) $invoice->due_date).'</p>' : '').$paymentTerms.'</div></header>'
            .'<section class="parties"><div class="panel"><h2>Furnizor</h2>'.$this->partyHtml($seller).'</div><div class="panel"><h2>Client</h2>'.$this->partyHtml($buyer).'</div></section>'
            .$correction.'<h2>Poziții factură</h2><table><thead><tr><th>#</th><th>Descriere / detalii taxă</th><th class="num">Cantitate</th><th class="num">Preț unitar net</th><th class="num">Reducere</th><th class="num">Valoare netă</th></tr></thead><tbody>'.$lines.'</tbody></table>'
            .'<h2>Defalcare taxe</h2><table><thead><tr><th>Schemă</th><th>Categorie</th><th class="num">Cotă</th><th class="num">Bază impozabilă</th><th class="num">Valoare taxă</th></tr></thead><tbody>'.$taxRows.'</tbody></table>'
            .'<table class="totals"><tbody><tr><td>Total net</td><td class="num">'.$this->money($invoice->net_total, $invoice->currency_code).'</td></tr><tr><td>Total taxe</td><td class="num">'.$this->money($invoice->tax_total, $invoice->currency_code).'</td></tr><tr><td class="grand">Total de plată</td><td class="num grand">'.$this->money($invoice->gross_total, $invoice->currency_code).'</td></tr></tbody></table>'
            .'<footer class="foot"><p>Referință document: '.$this->escape($invoice->public_id).'</p><p>Amprentă SHA-256 a instantaneului emis: '.$this->escape((string) $invoice->issued_snapshot_hash).'</p><p>Document HTML imprimabil generat din instantaneul emis. Formatul nu reprezintă XML e-Factura și nu confirmă transmiterea sau acceptarea de către ANAF.</p></footer></body></html>';
    }

    private function partyHtml(object $party): string
    {
        $address = collect([$party->address_line_1, $party->address_line_2, $party->postal_code, $party->locality, $party->region, $party->country_code])
            ->filter(fn ($value): bool => filled($value))->map(fn ($value): string => $this->escape((string) $value))->implode(', ');
        $identifiers = collect([
            $party->tax_identifier_type && $party->tax_identifier ? $party->tax_identifier_type.': '.$party->tax_identifier : null,
            $party->vat_registration_id ? 'TVA: '.$party->vat_registration_id : null,
            $party->registration_id ? 'Nr. înregistrare: '.$party->registration_id : null,
            $party->email,
        ])->filter(fn ($value): bool => filled($value))->map(fn ($value): string => '<p>'.$this->escape((string) $value).'</p>')->implode('');

        return '<p><strong>'.$this->escape($party->legal_name).'</strong></p>'.($address !== '' ? '<p>'.$address.'</p>' : '').$identifiers;
    }

    private function money(string|int|float|null $amount, string $currency): string
    {
        $value = trim((string) $amount);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $parts)) {
            throw new RuntimeException('Invoice amount has an unsupported decimal representation.');
        }
        $fraction = $parts[3] ?? '';
        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new RuntimeException('Invoice amount has more precision than the approved display format.');
        }
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[2]);
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        return $this->escape($parts[1].$integer.','.$fraction.' '.$currency);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
