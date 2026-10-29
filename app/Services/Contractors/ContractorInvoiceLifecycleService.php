<?php

namespace App\Services\Contractors;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Models\VendorInvoice;
use App\Models\VendorInvoiceEvent;
use App\Models\WorkerEngagement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ContractorInvoiceLifecycleService
{
    public function submit(int $invoiceId, User $actor): VendorInvoice
    {
        Gate::forUser($actor)->authorize('vendor_invoice.update');

        return DB::transaction(function () use ($invoiceId, $actor): VendorInvoice {
            $invoice = VendorInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            Vendor::query()->lockForUpdate()->findOrFail($invoice->vendor_id);
            if (! in_array($invoice->workflow_status, ['unresolved', 'rejected'], true)) {
                throw ValidationException::withMessages(['workflow_status' => 'Only unresolved or rejected invoices can be submitted for review.']);
            }
            /** @var WorkerEngagement|null $engagement */
            $engagement = $invoice->workerEngagement;
            if (! $engagement || $engagement->engagement_type !== 'pfa_contractor' || $engagement->status !== 'active') {
                throw ValidationException::withMessages(['worker_engagement_id' => 'An active, explicitly classified PFA contractor engagement is required.']);
            }
            if ((int) $engagement->vendor_id !== (int) $invoice->vendor_id || ! $invoice->vendor_contract_id
                || (int) $engagement->vendor_contract_id !== (int) $invoice->vendor_contract_id) {
                throw ValidationException::withMessages(['vendor_contract_id' => 'Invoice vendor and contract must match the contractor engagement.']);
            }
            if (blank($invoice->invoice_number) || blank($invoice->identifier) || blank($invoice->invoice) || blank($invoice->month)) {
                throw ValidationException::withMessages(['invoice_number' => 'Invoice number, source identifier, document reference, and billing period are required before review.']);
            }
            /** @var VendorContract|null $contract */
            $contract = $invoice->vendorContract;
            try {
                $billingDate = Carbon::parse((string) $invoice->month)->toDateString();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['month' => 'Enter a valid invoice billing period.']);
            }
            if (! $contract || Carbon::parse($billingDate)->endOfMonth()->toDateString() < (string) $contract->start_date || Carbon::parse($billingDate)->startOfMonth()->toDateString() > (string) $contract->end_date) {
                throw ValidationException::withMessages(['month' => 'The invoice billing period must fall within the linked contract dates.']);
            }
            $duplicate = VendorInvoice::query()->where('vendor_id', $invoice->vendor_id)
                ->where('invoice_number', $invoice->invoice_number)->whereKeyNot($invoice->getKey())->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['invoice_number' => 'This vendor already has an invoice with that invoice number.']);
            }
            $from = (string) $invoice->workflow_status;
            $invoice->forceFill(['workflow_status' => 'submitted', 'submitted_by' => $actor->getKey(), 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_reason' => null])->save();
            $this->event($invoice, 'submitted', $from, 'submitted', $actor, null);

            return $invoice->fresh();
        });
    }

    public function approve(int $invoiceId, User $actor, string $reason): VendorInvoice
    {
        Gate::forUser($actor)->authorize('vendor_invoice.update');
        $reason = $this->requiredReason($reason);

        return DB::transaction(function () use ($invoiceId, $actor, $reason): VendorInvoice {
            $invoice = VendorInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->workflow_status !== 'submitted') {
                throw ValidationException::withMessages(['workflow_status' => 'Only submitted invoices can be approved.']);
            }
            $from = (string) $invoice->workflow_status;
            $invoice->forceFill(['workflow_status' => 'approved', 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_reason' => $reason])->save();
            $this->event($invoice, 'approved', $from, 'approved', $actor, $reason, ['tax_treatment' => 'unsupported_unconfigured']);

            return $invoice->fresh();
        });
    }

    public function reject(int $invoiceId, User $actor, string $reason): VendorInvoice
    {
        Gate::forUser($actor)->authorize('vendor_invoice.update');
        $reason = $this->requiredReason($reason);

        return DB::transaction(function () use ($invoiceId, $actor, $reason): VendorInvoice {
            $invoice = VendorInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->workflow_status !== 'submitted') {
                throw ValidationException::withMessages(['workflow_status' => 'Only submitted invoices can be rejected.']);
            }
            $from = (string) $invoice->workflow_status;
            $invoice->forceFill(['workflow_status' => 'rejected', 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_reason' => $reason])->save();
            $this->event($invoice, 'rejected', $from, 'rejected', $actor, $reason);

            return $invoice->fresh();
        });
    }

    public function markPayable(int $invoiceId, User $actor, string $reason, ?string $payableOn = null): VendorInvoice
    {
        Gate::forUser($actor)->authorize('vendor_invoice.update');
        $reason = $this->requiredReason($reason);
        if ($payableOn !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $payableOn)) {
            throw ValidationException::withMessages(['payable_on' => 'Payable date must use YYYY-MM-DD format.']);
        }

        return DB::transaction(function () use ($invoiceId, $actor, $reason, $payableOn): VendorInvoice {
            $invoice = VendorInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->workflow_status !== 'approved') {
                throw ValidationException::withMessages(['workflow_status' => 'Only approved invoices can be marked payable.']);
            }
            $from = (string) $invoice->workflow_status;
            $invoice->forceFill(['workflow_status' => 'payable', 'payable_on' => $payableOn, 'review_reason' => $reason])->save();
            $this->event($invoice, 'marked_payable', $from, 'payable', $actor, $reason);

            return $invoice->fresh();
        });
    }

    public function configureTaxTreatment(int $invoiceId, User $actor, string $ruleKey): VendorInvoice
    {
        Gate::forUser($actor)->authorize('vendor_invoice.update');
        $ruleKey = trim($ruleKey);
        $rules = config('contractor-tax.rules', []);
        $rule = is_array($rules) ? ($rules[$ruleKey] ?? null) : null;
        if ($ruleKey === '' || ! is_array($rule) || blank($rule['version'] ?? null) || ($rule['supported'] ?? false) !== true) {
            throw ValidationException::withMessages(['tax_treatment_rule_key' => 'No reviewed, supported tax-treatment rule is configured for this key. Tax treatment remains unsupported.']);
        }

        return DB::transaction(function () use ($invoiceId, $actor, $ruleKey, $rule): VendorInvoice {
            $invoice = VendorInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if (! in_array($invoice->workflow_status, ['unresolved', 'rejected', 'submitted'], true)) {
                throw ValidationException::withMessages(['workflow_status' => 'Tax-treatment rule references can only be changed before approval.']);
            }
            $month = Carbon::parse((string) $invoice->month)->startOfMonth()->toDateString();
            if (isset($rule['effective_from']) && $month < (string) $rule['effective_from']) {
                throw ValidationException::withMessages(['tax_treatment_rule_key' => 'The configured rule is not effective for this invoice period.']);
            }
            if (isset($rule['effective_until']) && $month > (string) $rule['effective_until']) {
                throw ValidationException::withMessages(['tax_treatment_rule_key' => 'The configured rule has expired for this invoice period.']);
            }
            $from = (string) $invoice->workflow_status;
            $invoice->forceFill(['tax_treatment_status' => 'configured', 'tax_treatment_rule_key' => $ruleKey, 'tax_treatment_rule_version' => (string) $rule['version']])->save();
            $this->event($invoice, 'tax_treatment_configured', $from, $from, $actor, 'Configured rule reference only; no tax calculation was performed.', ['rule_key' => $ruleKey, 'rule_version' => (string) $rule['version']]);

            return $invoice->fresh();
        });
    }

    public function reverse(int $invoiceId, User $actor, string $reason): VendorInvoice
    {
        Gate::forUser($actor)->authorize('vendor_invoice.update');
        $reason = $this->requiredReason($reason);

        return DB::transaction(function () use ($invoiceId, $actor, $reason): VendorInvoice {
            $invoice = VendorInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if (! in_array($invoice->workflow_status, ['approved', 'payable'], true)) {
                throw ValidationException::withMessages(['workflow_status' => 'Only approved or payable invoices without a correction can be reversed.']);
            }
            if ($invoice->paymentAllocations()->whereIn('status', ['proposed', 'confirmed'])->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Resolve proposed and confirmed payment allocations before reversing the invoice.']);
            }
            $from = (string) $invoice->workflow_status;
            $invoice->forceFill(['workflow_status' => 'reversed', 'reversal_reason' => $reason, 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now()])->save();
            $this->event($invoice, 'reversed', $from, 'reversed', $actor, $reason);

            return $invoice->fresh();
        });
    }

    /** @param array<string, mixed> $correctedFields */
    public function correct(int $invoiceId, User $actor, array $correctedFields, string $reason): VendorInvoice
    {
        Gate::forUser($actor)->authorize('vendor_invoice.update');
        $reason = $this->requiredReason($reason);
        $allowed = ['amount', 'amount_without_vat', 'vat', 'currency', 'amount_decimal', 'amount_without_vat_decimal', 'vat_decimal', 'currency_code', 'invoice', 'invoice_number', 'identifier', 'month'];
        if (array_diff(array_keys($correctedFields), $allowed) !== []) {
            throw ValidationException::withMessages(['correction' => 'Correction contains unsupported fields.']);
        }

        return DB::transaction(function () use ($invoiceId, $actor, $correctedFields, $reason, $allowed): VendorInvoice {
            $original = VendorInvoice::query()->lockForUpdate()->findOrFail($invoiceId);
            if (! in_array($original->workflow_status, ['approved', 'payable'], true)) {
                throw ValidationException::withMessages(['workflow_status' => 'Only approved or payable invoices can be corrected.']);
            }
            if ($original->paymentAllocations()->where('status', 'confirmed')->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Resolve proposed and confirmed payment allocations before creating a correction.']);
            }
            if (blank($correctedFields['invoice_number'] ?? null) || blank($correctedFields['identifier'] ?? null) || blank($correctedFields['invoice'] ?? null)) {
                throw ValidationException::withMessages(['correction' => 'A correction must provide a new invoice number, source identifier, and document reference.']);
            }
            if ((string) $correctedFields['invoice_number'] === (string) $original->invoice_number || (string) $correctedFields['identifier'] === (string) $original->identifier) {
                throw ValidationException::withMessages(['correction' => 'A correction must have a distinct invoice number and source identifier.']);
            }
            $correction = $original->replicate();
            $correction->forceFill(array_merge($original->only($allowed), $correctedFields, [
                'workflow_status' => 'unresolved', 'submitted_by' => null, 'submitted_at' => null,
                'reviewed_by' => null, 'reviewed_at' => null, 'review_reason' => null, 'payable_on' => null,
                'corrects_vendor_invoice_id' => $original->getKey(), 'correction_reason' => $reason,
                'reversal_reason' => null, 'tax_treatment_status' => 'unsupported', 'tax_treatment_rule_key' => null, 'tax_treatment_rule_version' => null,
            ]));
            $correction->save();
            $from = (string) $original->workflow_status;
            $original->forceFill(['workflow_status' => 'corrected', 'correction_reason' => $reason])->save();
            $this->event($original, 'corrected', $from, 'corrected', $actor, $reason, ['correction_invoice_id' => $correction->getKey()]);
            $this->event($correction, 'correction_created', null, 'unresolved', $actor, $reason, ['corrects_vendor_invoice_id' => $original->getKey()]);

            return $correction->fresh();
        });
    }

    private function event(VendorInvoice $invoice, string $type, ?string $from, string $to, User $actor, ?string $reason, array $metadata = []): void
    {
        VendorInvoiceEvent::query()->create([
            'vendor_invoice_id' => $invoice->getKey(), 'event_type' => $type,
            'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor->getKey(),
            'reason' => $reason, 'metadata' => $metadata, 'occurred_at' => now(),
        ]);
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'A reason of 1 to 1000 characters is required.']);
        }

        return $reason;
    }
}
