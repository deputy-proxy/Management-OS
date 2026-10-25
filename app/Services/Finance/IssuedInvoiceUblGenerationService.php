<?php

namespace App\Services\Finance;

use App\Models\IssuedInvoice;
use App\Models\IssuedInvoiceArtifact;
use App\Models\IssuedInvoiceParty;
use App\Models\User;
use Brick\Math\BigDecimal;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class IssuedInvoiceUblGenerationService
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const CATEGORIES = ['STANDARD' => 'S', 'REDUCED' => 'S', 'SUPER_REDUCED' => 'S', 'ZERO' => 'Z', 'EXEMPT' => 'E', 'REVERSE_CHARGE' => 'AE', 'OUT_OF_SCOPE' => 'O', 'INTRA_COMMUNITY' => 'K', 'EXPORT' => 'G', 'S' => 'S', 'Z' => 'Z', 'E' => 'E', 'AE' => 'AE', 'O' => 'O', 'K' => 'K', 'G' => 'G'];

    public function __construct(private readonly IssuedInvoiceSnapshotIntegrityService $integrity, private readonly RomanianEfacturaUblValidator $validator) {}

    public function generate(int $invoiceId, User $actor): IssuedInvoiceArtifact
    {
        Gate::forUser($actor)->authorize('customer_invoice.update');

        return DB::transaction(function () use ($invoiceId, $actor): IssuedInvoiceArtifact {
            $invoice = IssuedInvoice::query()->lockForUpdate()->with(['legalEntity', 'parties', 'lines.taxes', 'taxBreakdowns', 'events', 'correctedInvoice'])->findOrFail($invoiceId);
            if (! in_array($invoice->document_status, [IssuedInvoice::STATUS_ISSUED, IssuedInvoice::STATUS_CORRECTED], true)) {
                throw ValidationException::withMessages(['document_status' => 'Only issued or corrected invoices can be generated as e-Factura XML.']);
            }
            if ($invoice->document_type === IssuedInvoice::TYPE_DEBIT_NOTE) {
                throw ValidationException::withMessages(['document_type' => 'Debit-note XML mapping is disabled until its current RO profile mapping is approved.']);
            }
            if (! in_array($invoice->document_type, [IssuedInvoice::TYPE_INVOICE, IssuedInvoice::TYPE_CREDIT_NOTE], true)) {
                throw ValidationException::withMessages(['document_type' => 'This document type is not supported by the pinned UBL 2.1 generator.']);
            }
            $this->integrity->assertValid($invoice);
            $existing = $invoice->artifacts()->where('artifact_type', 'ubl_xml')->latest('id')->first();
            if ($existing !== null) {
                $disk = Storage::disk($existing->storage_disk);
                if (! $disk->exists($existing->storage_key)) {
                    throw ValidationException::withMessages(['artifact' => 'Previously generated XML is missing from private storage; restore it from backup.']);
                }
                $bytes = $disk->get($existing->storage_key);
                if (! hash_equals($existing->sha256, hash('sha256', $bytes)) || strlen($bytes) !== $existing->byte_size) {
                    throw ValidationException::withMessages(['artifact' => 'Previously generated XML failed integrity checks; restore the verified artifact.']);
                }
                $this->validator->validate($bytes);

                return $existing;
            }
            $xml = $this->render($invoice);
            $this->validator->validate($xml);
            $sha = hash('sha256', $xml);
            $key = 'issued-invoices/'.$invoice->public_id.'/'.$invoice->issued_snapshot_hash.'/ubl-'.$sha.'.xml';
            $disk = Storage::disk('local');
            if (! $disk->put($key, $xml)) {
                throw new RuntimeException('Could not write generated UBL XML to private storage.');
            }
            try {
                return $invoice->artifacts()->create(['artifact_type' => 'ubl_xml', 'storage_disk' => 'local', 'storage_key' => $key, 'media_type' => 'application/xml; charset=UTF-8', 'byte_size' => strlen($xml), 'sha256' => $sha, 'created_by' => $actor->getKey()]);
            } catch (Throwable $exception) {
                $disk->delete($key);
                throw $exception;
            }
        }, attempts: 3);
    }

    private function render(IssuedInvoice $invoice): string
    {
        $seller = $invoice->parties->firstWhere('role', 'seller');
        $buyer = $invoice->parties->firstWhere('role', 'buyer');
        if (! $seller instanceof IssuedInvoiceParty || ! $buyer instanceof IssuedInvoiceParty || $invoice->lines->isEmpty() || $invoice->taxBreakdowns->isEmpty()) {
            throw ValidationException::withMessages(['invoice' => 'Seller, buyer, lines and tax breakdowns are required.']);
        }
        if (strtoupper((string) $seller->country_code) !== 'RO' || blank($seller->tax_identifier)) {
            throw ValidationException::withMessages(['seller' => 'A Romanian seller with a fiscal identifier is required.']);
        }
        $credit = $invoice->document_type === IssuedInvoice::TYPE_CREDIT_NOTE;
        if ($credit && ($invoice->correctedInvoice === null || blank($invoice->correctedInvoice->invoice_number))) {
            throw ValidationException::withMessages(['corrects_issued_invoice_id' => 'Credit note requires a linked original issued invoice.']);
        }
        $ns = $credit ? 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2' : 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS($ns, $credit ? 'CreditNote' : 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $doc->appendChild($root);
        $this->text($doc, $root, self::CBC, 'cbc:UBLVersionID', '2.1');
        $this->text($doc, $root, self::CBC, 'cbc:CustomizationID', RomanianEfacturaUblValidator::PROFILE_URN);
        $this->text($doc, $root, self::CBC, 'cbc:ID', trim((string) $invoice->series).'/'.trim((string) $invoice->invoice_number));
        $this->text($doc, $root, self::CBC, 'cbc:IssueDate', substr((string) $invoice->issue_date, 0, 10));
        if ($invoice->due_date && ! $credit) {
            $this->text($doc, $root, self::CBC, 'cbc:DueDate', substr((string) $invoice->due_date, 0, 10));
        }
        $this->text($doc, $root, self::CBC, $credit ? 'cbc:CreditNoteTypeCode' : 'cbc:InvoiceTypeCode', $credit ? '381' : '380');
        $this->text($doc, $root, self::CBC, 'cbc:DocumentCurrencyCode', (string) $invoice->currency_code);
        if ($credit) {
            $ref = $this->element($doc, $root, self::CAC, 'cac:BillingReference');
            $original = $this->element($doc, $ref, self::CAC, 'cac:InvoiceDocumentReference');
            $this->text($doc, $original, self::CBC, 'cbc:ID', trim((string) $invoice->correctedInvoice->series).'/'.trim((string) $invoice->correctedInvoice->invoice_number));
            $this->text($doc, $original, self::CBC, 'cbc:IssueDate', substr((string) $invoice->correctedInvoice->issue_date, 0, 10));
        }
        $this->party($doc, $root, 'AccountingSupplierParty', $seller);
        $this->party($doc, $root, 'AccountingCustomerParty', $buyer);
        foreach ($invoice->taxBreakdowns as $tax) {
            $this->category($tax);
        }
        $taxTotal = $this->element($doc, $root, self::CAC, 'cac:TaxTotal');
        $this->text($doc, $taxTotal, self::CBC, 'cbc:TaxAmount', (string) $invoice->tax_total, ['currencyID' => $invoice->currency_code]);
        foreach ($invoice->taxBreakdowns->sortBy(['tax_scheme_code', 'tax_category_code'])->values() as $tax) {
            $category = $this->category($tax);
            $sub = $this->element($doc, $taxTotal, self::CAC, 'cac:TaxSubtotal');
            $this->text($doc, $sub, self::CBC, 'cbc:TaxableAmount', (string) $tax->taxable_base, ['currencyID' => $invoice->currency_code]);
            $this->text($doc, $sub, self::CBC, 'cbc:TaxAmount', (string) $tax->tax_amount, ['currencyID' => $invoice->currency_code]);
            $cat = $this->element($doc, $sub, self::CAC, 'cac:TaxCategory');
            $this->text($doc, $cat, self::CBC, 'cbc:ID', $category);
            if ($tax->tax_rate_percent !== null) {
                $this->text($doc, $cat, self::CBC, 'cbc:Percent', (string) $tax->tax_rate_percent);
            }
            if (filled($tax->exemption_reason_code)) {
                $this->text($doc, $cat, self::CBC, 'cbc:TaxExemptionReasonCode', (string) $tax->exemption_reason_code);
            }
            if (filled($tax->exemption_reason)) {
                $this->text($doc, $cat, self::CBC, 'cbc:TaxExemptionReason', (string) $tax->exemption_reason);
            }
            $scheme = $this->element($doc, $cat, self::CAC, 'cac:TaxScheme');
            $this->text($doc, $scheme, self::CBC, 'cbc:ID', 'VAT');
        }
        $totals = $this->element($doc, $root, self::CAC, 'cac:LegalMonetaryTotal');
        $this->text($doc, $totals, self::CBC, 'cbc:LineExtensionAmount', (string) $invoice->net_total, ['currencyID' => $invoice->currency_code]);
        $this->text($doc, $totals, self::CBC, 'cbc:TaxExclusiveAmount', (string) $invoice->net_total, ['currencyID' => $invoice->currency_code]);
        $this->text($doc, $totals, self::CBC, 'cbc:TaxInclusiveAmount', (string) $invoice->gross_total, ['currencyID' => $invoice->currency_code]);
        $this->text($doc, $totals, self::CBC, 'cbc:PayableAmount', (string) $invoice->gross_total, ['currencyID' => $invoice->currency_code]);
        foreach ($invoice->lines->sortBy('line_number')->values() as $line) {
            if ($line->taxes->count() !== 1) {
                throw ValidationException::withMessages(['lines.'.$line->line_number.'.taxes' => 'Each line must use exactly one VAT category for this approved mapping.']);
            }
            $tax = $line->taxes->first();
            $category = $this->category($tax);
            $lineElement = $this->element($doc, $root, self::CAC, $credit ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
            $this->text($doc, $lineElement, self::CBC, 'cbc:ID', (string) $line->line_number);
            $this->text($doc, $lineElement, self::CBC, $credit ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity', (string) $line->quantity, ['unitCode' => (string) $line->unit_code]);
            $this->text($doc, $lineElement, self::CBC, 'cbc:LineExtensionAmount', (string) $line->net_amount, ['currencyID' => $invoice->currency_code]);
            if (BigDecimal::of((string) $line->discount_amount)->isGreaterThan('0')) {
                $allow = $this->element($doc, $lineElement, self::CAC, 'cac:AllowanceCharge');
                $this->text($doc, $allow, self::CBC, 'cbc:ChargeIndicator', 'false');
                $this->text($doc, $allow, self::CBC, 'cbc:AllowanceChargeReason', 'Reducere');
                $this->text($doc, $allow, self::CBC, 'cbc:Amount', (string) $line->discount_amount, ['currencyID' => $invoice->currency_code]);
            }
            $item = $this->element($doc, $lineElement, self::CAC, 'cac:Item');
            $this->text($doc, $item, self::CBC, 'cbc:Name', (string) $line->description);
            $classified = $this->element($doc, $item, self::CAC, 'cac:ClassifiedTaxCategory');
            $this->text($doc, $classified, self::CBC, 'cbc:ID', $category);
            if ($tax->tax_rate_percent !== null) {
                $this->text($doc, $classified, self::CBC, 'cbc:Percent', (string) $tax->tax_rate_percent);
            }
            if (filled($tax->exemption_reason_code)) {
                $this->text($doc, $classified, self::CBC, 'cbc:TaxExemptionReasonCode', (string) $tax->exemption_reason_code);
            }
            if (filled($tax->exemption_reason)) {
                $this->text($doc, $classified, self::CBC, 'cbc:TaxExemptionReason', (string) $tax->exemption_reason);
            }
            $scheme = $this->element($doc, $classified, self::CAC, 'cac:TaxScheme');
            $this->text($doc, $scheme, self::CBC, 'cbc:ID', 'VAT');
            $price = $this->element($doc, $lineElement, self::CAC, 'cac:Price');
            $this->text($doc, $price, self::CBC, 'cbc:PriceAmount', (string) $line->unit_price_net, ['currencyID' => $invoice->currency_code]);
            $this->text($doc, $price, self::CBC, 'cbc:BaseQuantity', '1', ['unitCode' => (string) $line->unit_code]);
        }

        return $doc->saveXML() ?: throw new RuntimeException('Could not serialize UBL XML.');
    }

    private function party(DOMDocument $doc, DOMElement $root, string $group, IssuedInvoiceParty $party): void
    {
        $country = strtoupper((string) $party->country_code);
        if (blank($party->legal_name) || blank($party->locality) || ! preg_match('/^[A-Z]{2}$/', $country)) {
            throw ValidationException::withMessages(['party' => 'Legal name, city and ISO country code are required.']);
        }
        if (filled($party->region) && ! preg_match('/^RO-[A-Z]{1,2}$/', strtoupper((string) $party->region))) {
            throw ValidationException::withMessages(['party.region' => 'Romanian region must use an ISO 3166-2:RO code.']);
        }
        $groupElement = $this->element($doc, $root, self::CAC, 'cac:'.$group);
        $partyElement = $this->element($doc, $groupElement, self::CAC, 'cac:Party');
        $taxId = trim((string) $party->tax_identifier);
        if (blank($party->endpoint_id) || blank($party->endpoint_scheme_id)) {
            throw ValidationException::withMessages(['party.endpoint_id' => 'An explicit electronic endpoint identifier and ISO 6523 scheme code are required; fiscal IDs are not guessed as endpoint IDs.']);
        }
        if (! preg_match('/^\d{4}$/', (string) $party->endpoint_scheme_id)) {
            throw ValidationException::withMessages(['party.endpoint_scheme_id' => 'Endpoint scheme must be a four-digit ISO 6523 ICD code.']);
        }
        $this->text($doc, $partyElement, self::CBC, 'cbc:EndpointID', (string) $party->endpoint_id, ['schemeID' => (string) $party->endpoint_scheme_id]);
        if ($taxId !== '' && $country === 'RO' && in_array(strtoupper((string) $party->tax_identifier_type), ['CUI', 'CIF'], true)) {
            $cui = preg_replace('/^RO/i', '', $taxId);
            if (! preg_match('/^\d{2,10}$/', (string) $cui)) {
                throw ValidationException::withMessages(['party.tax_identifier' => 'Romanian CUI/CIF must contain 2 to 10 digits.']);
            }
            $ident = $this->element($doc, $partyElement, self::CAC, 'cac:PartyIdentification');
            $this->text($doc, $ident, self::CBC, 'cbc:ID', (string) $cui);
        }
        $name = $this->element($doc, $partyElement, self::CAC, 'cac:PartyName');
        $this->text($doc, $name, self::CBC, 'cbc:Name', (string) $party->legal_name);
        $address = $this->element($doc, $partyElement, self::CAC, 'cac:PostalAddress');
        if (filled($party->address_line_1)) {
            $this->text($doc, $address, self::CBC, 'cbc:StreetName', (string) $party->address_line_1);
        }
        if (filled($party->address_line_2)) {
            $this->text($doc, $address, self::CBC, 'cbc:AdditionalStreetName', (string) $party->address_line_2);
        }
        $this->text($doc, $address, self::CBC, 'cbc:CityName', (string) $party->locality);
        if (filled($party->postal_code)) {
            $this->text($doc, $address, self::CBC, 'cbc:PostalZone', (string) $party->postal_code);
        }
        if (filled($party->region)) {
            $this->text($doc, $address, self::CBC, 'cbc:CountrySubentity', strtoupper((string) $party->region));
        }
        $countryElement = $this->element($doc, $address, self::CAC, 'cac:Country');
        $this->text($doc, $countryElement, self::CBC, 'cbc:IdentificationCode', $country);
        $vat = filled($party->vat_registration_id) ? strtoupper((string) $party->vat_registration_id) : (($country === 'RO' && $taxId !== '' && in_array(strtoupper((string) $party->tax_identifier_type), ['CUI', 'CIF'], true)) ? 'RO'.preg_replace('/^RO/i', '', $taxId) : null);
        if ($vat !== null) {
            $taxScheme = $this->element($doc, $partyElement, self::CAC, 'cac:PartyTaxScheme');
            $this->text($doc, $taxScheme, self::CBC, 'cbc:CompanyID', $vat);
            $scheme = $this->element($doc, $taxScheme, self::CAC, 'cac:TaxScheme');
            $this->text($doc, $scheme, self::CBC, 'cbc:ID', 'VAT');
        }
        $legal = $this->element($doc, $partyElement, self::CAC, 'cac:PartyLegalEntity');
        $this->text($doc, $legal, self::CBC, 'cbc:RegistrationName', (string) $party->legal_name);
        if (filled($party->registration_id)) {
            $this->text($doc, $legal, self::CBC, 'cbc:CompanyID', (string) $party->registration_id);
        }
        if (filled($party->email)) {
            $contact = $this->element($doc, $partyElement, self::CAC, 'cac:Contact');
            $this->text($doc, $contact, self::CBC, 'cbc:ElectronicMail', (string) $party->email);
        }
    }

    private function category(object $tax): string
    {
        if (strtoupper((string) $tax->tax_scheme_code) !== 'VAT') {
            throw ValidationException::withMessages(['tax_scheme_code' => 'Only VAT is supported by this mapping.']);
        }
        $category = self::CATEGORIES[strtoupper((string) $tax->tax_category_code)] ?? null;
        if ($category === null) {
            throw ValidationException::withMessages(['tax_category_code' => 'Unsupported tax category. Add an approved explicit mapping first.']);
        }
        if (($tax->reverse_charge ?? false) && $category !== 'AE') {
            throw ValidationException::withMessages(['tax_category_code' => 'Reverse charge requires UBL category AE.']);
        }
        if ($category === 'E' && blank($tax->exemption_reason_code) && blank($tax->exemption_reason)) {
            throw ValidationException::withMessages(['exemption_reason' => 'Exempt VAT requires an exemption reason.']);
        }

        return $category;
    }

    /** @param array<string, string> $attributes */
    private function text(DOMDocument $doc, DOMElement $parent, string $ns, string $name, string $value, array $attributes = []): DOMElement
    {
        $element = $this->element($doc, $parent, $ns, $name, $value);
        foreach ($attributes as $key => $val) {
            $element->setAttribute($key, $val);
        }

        return $element;
    }

    private function element(DOMDocument $doc, DOMElement $parent, string $ns, string $name, ?string $value = null): DOMElement
    {
        $element = $doc->createElementNS($ns, $name);
        if ($value !== null) {
            $element->appendChild($doc->createTextNode($value));
        } $parent->appendChild($element);

        return $element;
    }
}
