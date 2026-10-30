<?php

namespace Tests\Feature;

use App\Models\EmployeePaySlip;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Models\VendorInvoice;
use App\Models\VendorInvoiceEvent;
use App\Models\WorkerEngagement;
use App\Services\Contractors\ContractorInvoiceLifecycleService;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContractorInvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('vendor_invoice.update', static fn (): bool => true);
        Gate::define('transaction.update', static fn (): bool => true);
    }

    public function test_pfa_invoice_can_be_submitted_approved_and_marked_payable_with_immutable_events(): void
    {
        $payslipCountBefore = EmployeePaySlip::query()->count();
        [$invoice, $actor] = $this->fixture();
        $service = app(ContractorInvoiceLifecycleService::class);

        $this->assertSame('submitted', $service->submit($invoice->id, $actor)->workflow_status);
        $approved = $service->approve($invoice->id, $actor, 'Synthetic invoice reviewed against the contract.');
        $this->assertSame('approved', $approved->workflow_status);
        $this->assertSame('unsupported', $approved->tax_treatment_status);
        $payable = $service->markPayable($invoice->id, $actor, 'Approved for payment; settlement remains unconfirmed.', '2026-11-15');
        $this->assertSame('payable', $payable->workflow_status);
        $this->assertSame('2026-11-15', $payable->payable_on->toDateString());
        $this->assertSame(['submitted', 'approved', 'payable'], VendorInvoiceEvent::query()->where('vendor_invoice_id', $invoice->id)->orderBy('id')->pluck('to_status')->all());
        $this->assertSame($payslipCountBefore, EmployeePaySlip::query()->count());
    }

    public function test_submission_requires_explicit_active_pfa_engagement_and_source_document(): void
    {
        [$invoice, $actor] = $this->fixture(['worker_engagement_id' => null]);

        try {
            app(ContractorInvoiceLifecycleService::class)->submit($invoice->id, $actor);
            $this->fail('Invoice without a PFA engagement must not enter review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('worker_engagement_id', $exception->errors());
        }
    }

    public function test_missing_source_document_blocks_submission(): void
    {
        [$invoice, $actor] = $this->fixture(['invoice' => '']);

        try {
            app(ContractorInvoiceLifecycleService::class)->submit($invoice->id, $actor);
            $this->fail('Invoices without source documents must not enter review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_number', $exception->errors());
        }
    }

    public function test_lifecycle_service_enforces_update_permission(): void
    {
        [$invoice, $actor] = $this->fixture();
        Gate::define('vendor_invoice.update', static fn (): bool => false);

        $this->expectException(AuthorizationException::class);
        app(ContractorInvoiceLifecycleService::class)->submit($invoice->id, $actor);
    }

    public function test_contractor_payment_allocation_requires_payable_state_and_links_confirmed_payment(): void
    {
        [$invoice, $actor] = $this->fixture();
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $payment = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'timestamp' => '2026-01-15 12:00:00',
            'amount' => 100,
            'amount_decimal' => '100.00',
            'currency_code' => 'RON',
            'transaction_type_code' => 'money_out',
        ]);
        $allocations = app(PaymentAllocationService::class);

        try {
            $allocations->propose($actor, $payment->id, 'invoice', '100.00', 'RON', 'vendor_invoice', $invoice->id, 'Synthetic early payment attempt');
            $this->fail('A contractor invoice cannot be allocated before approval and payable state.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target_id', $exception->errors());
        }

        $lifecycle = app(ContractorInvoiceLifecycleService::class);
        $lifecycle->submit($invoice->id, $actor);
        $lifecycle->approve($invoice->id, $actor, 'Synthetic contract and invoice review.');
        $lifecycle->markPayable($invoice->id, $actor, 'Synthetic payment authorization.');
        $allocation = $allocations->propose($actor, $payment->id, 'invoice', '100.00', 'RON', 'vendor_invoice', $invoice->id, 'Synthetic bank payment reference');
        $allocations->confirm($allocation->id, $actor);

        $this->assertCount(1, $invoice->fresh()->paymentAllocations);
        $this->assertSame('allocation_total_only', $allocations->invoiceSummary('vendor_invoice', $invoice->id)['status']);
        $this->assertSame('payable', $invoice->fresh()->workflow_status);
    }

    public function test_partial_payments_from_distinct_transactions_accumulate_without_inventing_invoice_settlement(): void
    {
        [$invoice, $actor] = $this->fixture();
        $lifecycle = app(ContractorInvoiceLifecycleService::class);
        $lifecycle->submit($invoice->id, $actor);
        $lifecycle->approve($invoice->id, $actor, 'Synthetic invoice review.');
        $lifecycle->markPayable($invoice->id, $actor, 'Synthetic payment authorization.');

        $allocations = app(PaymentAllocationService::class);
        $firstPayment = $this->paymentTransaction('40.00');
        $secondPayment = $this->paymentTransaction('30.00');
        $first = $allocations->propose($actor, $firstPayment->id, 'invoice', '40.00', 'RON', 'vendor_invoice', $invoice->id, 'First partial contractor payment');
        $second = $allocations->propose($actor, $secondPayment->id, 'invoice', '30.00', 'RON', 'vendor_invoice', $invoice->id, 'Second partial contractor payment');
        $allocations->confirm($first->id, $actor);
        $allocations->confirm($second->id, $actor);

        $summary = $allocations->invoiceSummary('vendor_invoice', $invoice->id);
        $this->assertSame('allocation_total_only', $summary['status']);
        $this->assertSame('70.00', $summary['allocated_total']);
        $this->assertSame(2, $summary['allocation_count']);
        $this->assertSame('unresolved_invoice_total_semantics', $summary['invoice_balance_validation']);
        $this->assertSame('payable', $invoice->fresh()->workflow_status);
        $this->assertSame('fully_allocated', $allocations->paymentSummary($firstPayment->fresh())['status']);
        $this->assertSame('fully_allocated', $allocations->paymentSummary($secondPayment->fresh())['status']);
    }

    public function test_duplicate_contractor_payment_allocation_is_blocked_until_original_is_reversed(): void
    {
        [$invoice, $actor] = $this->fixture();
        $lifecycle = app(ContractorInvoiceLifecycleService::class);
        $lifecycle->submit($invoice->id, $actor);
        $lifecycle->approve($invoice->id, $actor, 'Synthetic invoice review.');
        $lifecycle->markPayable($invoice->id, $actor, 'Synthetic payment authorization.');
        $allocations = app(PaymentAllocationService::class);
        $payment = $this->paymentTransaction('100.00');
        $first = $allocations->propose($actor, $payment->id, 'invoice', '35.00', 'RON', 'vendor_invoice', $invoice->id, 'Original payment allocation');
        $allocations->confirm($first->id, $actor);
        $duplicate = $allocations->propose($actor, $payment->id, 'invoice', '20.00', 'RON', 'vendor_invoice', $invoice->id, 'Replacement allocation proposal');

        try {
            $allocations->confirm($duplicate->id, $actor);
            $this->fail('A payment cannot be confirmed twice against the same contractor invoice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target_id', $exception->errors());
        }

        $allocations->reverse($first->id, $actor, 'Correcting the source invoice allocation reference.');
        $allocations->confirm($duplicate->id, $actor);
        $this->assertSame('confirmed', $duplicate->fresh()->status);
        $this->assertSame('20.00', $allocations->invoiceSummary('vendor_invoice', $invoice->id)['allocated_total']);
        $this->assertSame('payable', $invoice->fresh()->workflow_status);
    }

    public function test_invoice_reversal_requires_confirmed_payment_allocations_to_be_reversed_first(): void
    {
        [$invoice, $actor] = $this->fixture();
        $lifecycle = app(ContractorInvoiceLifecycleService::class);
        $lifecycle->submit($invoice->id, $actor);
        $lifecycle->approve($invoice->id, $actor, 'Synthetic invoice review.');
        $lifecycle->markPayable($invoice->id, $actor, 'Synthetic payment authorization.');
        $allocations = app(PaymentAllocationService::class);
        $payment = $this->paymentTransaction('50.00');
        $allocation = $allocations->propose($actor, $payment->id, 'invoice', '50.00', 'RON', 'vendor_invoice', $invoice->id, 'Synthetic confirmed payment');
        $allocations->confirm($allocation->id, $actor);

        try {
            $lifecycle->reverse($invoice->id, $actor, 'Attempted invoice reversal before allocation reversal.');
            $this->fail('Invoice reversal must wait for confirmed payment allocations to be reversed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }

        $allocations->reverse($allocation->id, $actor, 'Reversing the payment link before invoice reversal.');
        $this->assertSame('reversed', $lifecycle->reverse($invoice->id, $actor, 'Reversing the payable contractor invoice.')->workflow_status);
        $this->assertSame('reversed', $allocation->fresh()->status);
    }

    public function test_tax_treatment_is_unsupported_without_a_rule_and_can_only_reference_configured_versioned_rules(): void
    {
        [$invoice, $actor] = $this->fixture();
        $this->assertSame('unsupported', $invoice->tax_treatment_status);

        config(['contractor-tax.rules.synthetic-reviewed-rule' => [
            'version' => '2026-01', 'supported' => true, 'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31',
        ]]);
        $configured = app(ContractorInvoiceLifecycleService::class)->configureTaxTreatment($invoice->id, $actor, 'synthetic-reviewed-rule');
        $this->assertSame('configured', $configured->tax_treatment_status);
        $this->assertSame('synthetic-reviewed-rule', $configured->tax_treatment_rule_key);
        $this->assertSame('2026-01', $configured->tax_treatment_rule_version);
        $this->assertDatabaseHas('vendor_invoice_events', ['vendor_invoice_id' => $invoice->id, 'event_type' => 'tax_treatment_configured']);
    }

    public function test_invalid_transition_is_rejected_and_rejection_requires_a_reason(): void
    {
        [$invoice, $actor] = $this->fixture();
        $service = app(ContractorInvoiceLifecycleService::class);

        try {
            $service->approve($invoice->id, $actor, 'Reviewed');
            $this->fail('Unsubmitted invoice cannot be approved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow_status', $exception->errors());
        }

        $service->submit($invoice->id, $actor);
        try {
            $service->reject($invoice->id, $actor, ' ');
            $this->fail('Rejection must have a reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
        $this->assertSame('rejected', $service->reject($invoice->id, $actor, 'Missing supporting attachment.')->workflow_status);
    }

    public function test_approved_invoice_is_locked_and_correction_preserves_the_original(): void
    {
        [$invoice, $actor] = $this->fixture();
        $service = app(ContractorInvoiceLifecycleService::class);
        $service->submit($invoice->id, $actor);
        $service->approve($invoice->id, $actor, 'Reviewed.');

        try {
            $invoice->fresh()->forceFill(['amount' => 999])->save();
            $this->fail('Approved invoice values must be immutable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow_status', $exception->errors());
        }

        $correction = $service->correct($invoice->id, $actor, ['amount' => 125, 'invoice_number' => 'SYNTH-INV-001-C1', 'identifier' => 'synthetic-doc-001-c1', 'invoice' => 'synthetic://contractor-invoice/001-correction'], 'Corrected synthetic amount from source evidence.');
        $this->assertSame('corrected', $invoice->fresh()->workflow_status);
        $this->assertSame($invoice->id, $correction->corrects_vendor_invoice_id);
        $this->assertSame('unresolved', $correction->workflow_status);
        $this->assertSame('100', (string) $invoice->fresh()->amount);
        $this->assertDatabaseHas('vendor_invoice_events', ['vendor_invoice_id' => $invoice->id, 'event_type' => 'corrected']);
    }

    public function test_duplicate_invoice_number_for_same_vendor_is_blocked(): void
    {
        [$invoice, $actor] = $this->fixture();
        $duplicate = $invoice->replicate();
        $duplicate->forceFill(['identifier' => 'synthetic-doc-002', 'workflow_status' => 'unresolved']);
        $duplicate->save();

        try {
            app(ContractorInvoiceLifecycleService::class)->submit($duplicate->id, $actor);
            $this->fail('Duplicate vendor invoice number must be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_number', $exception->errors());
        }
    }

    public function test_lifecycle_event_records_cannot_be_updated_or_deleted(): void
    {
        [$invoice, $actor] = $this->fixture();
        app(ContractorInvoiceLifecycleService::class)->submit($invoice->id, $actor);
        $event = VendorInvoiceEvent::query()->where('vendor_invoice_id', $invoice->id)->firstOrFail();

        try {
            $event->forceFill(['reason' => 'rewritten history'])->save();
            $this->fail('Lifecycle event history must be immutable.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        try {
            $event->delete();
            $this->fail('Lifecycle event history must not be deleted.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
    }

    public function test_reversal_requires_reason_and_preserves_event_history(): void
    {
        [$invoice, $actor] = $this->fixture();
        $service = app(ContractorInvoiceLifecycleService::class);
        $service->submit($invoice->id, $actor);
        $service->approve($invoice->id, $actor, 'Reviewed.');

        $reversed = $service->reverse($invoice->id, $actor, 'Duplicate invoice confirmed during review.');
        $this->assertSame('reversed', $reversed->workflow_status);
        $this->assertSame('Duplicate invoice confirmed during review.', $reversed->reversal_reason);
        $this->assertDatabaseHas('vendor_invoice_events', ['vendor_invoice_id' => $invoice->id, 'event_type' => 'reversed']);
    }

    private function paymentTransaction(string $amount): Transaction
    {
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);

        return Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'vendor_invoice_id' => null,
            'customer_invoice_id' => null,
            'tax_id' => null,
            'timestamp' => '2026-01-15 12:00:00',
            'amount' => (float) $amount,
            'amount_decimal' => $amount,
            'currency_code' => 'RON',
            'transaction_type_code' => 'money_out',
        ]);
    }

    /** @return array{VendorInvoice, User} */
    private function fixture(array $overrides = []): array
    {
        $vendor = Vendor::factory()->create();
        $contract = VendorContract::factory()->create(['vendor_id' => $vendor->id, 'start_date' => '2026-01-01', 'end_date' => '2027-01-01']);
        $engagement = WorkerEngagement::factory()->create([
            'vendor_id' => $vendor->id, 'vendor_contract_id' => $contract->id,
            'engagement_type' => 'pfa_contractor', 'status' => 'active',
            'start_date' => '2026-01-01',
        ]);
        $invoice = VendorInvoice::factory()->create(array_merge([
            'vendor_id' => $vendor->id, 'vendor_contract_id' => $contract->id,
            'worker_engagement_id' => $engagement->id, 'workflow_status' => 'unresolved',
            'invoice_number' => 'SYNTH-INV-001', 'identifier' => 'synthetic-doc-001',
            'invoice' => 'synthetic://contractor-invoice/001', 'month' => '2026-01-01', 'currency' => 'RON', 'currency_code' => 'RON', 'amount_without_vat' => 100, 'vat' => 0, 'amount' => 100,
            'tax_treatment_status' => 'unsupported',
        ], $overrides));

        return [$invoice, User::factory()->create()];
    }
}
