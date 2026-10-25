<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\FinancialAccount;
use App\Models\PaymentAllocation;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Gate::before(static fn (): bool => true);
    }

    public function test_proposed_allocation_is_not_counted_until_confirmed_and_partial_payment_is_explicit(): void
    {
        [$payment, $actor, $customerInvoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $invoiceStatusBefore = $customerInvoice->status;
        $invoiceAmountBefore = $customerInvoice->getAttribute('amount_without_vat_decimal');
        $paymentAmountBefore = $payment->getAttribute('amount_decimal');
        $proposal = $service->propose($actor, $payment->id, 'invoice', '80.00', 'RON', 'customer_invoice', $customerInvoice->id, 'Partial payment evidenced by bank statement');

        $this->assertSame('proposed', $service->paymentSummary($payment)['status']);
        $this->assertSame(PaymentAllocation::STATUS_PROPOSED, $proposal->status);

        $service->confirm($proposal->id, $actor);
        $summary = $service->paymentSummary($payment->fresh());

        $this->assertSame('partially_allocated', $summary['status']);
        $this->assertSame('80.00', $summary['invoice_allocated']);
        $this->assertSame('45.00', $summary['unallocated']);
        $this->assertSame('unresolved_invoice_total_semantics', $summary['invoice_balance_validation']);
        $this->assertSame('RON', $summary['currency_code']);
        $this->assertSame($invoiceStatusBefore, $customerInvoice->fresh()->status);
        $this->assertSame($invoiceAmountBefore, $customerInvoice->fresh()->getAttribute('amount_without_vat_decimal'));
        $this->assertSame($paymentAmountBefore, $payment->fresh()->getAttribute('amount_decimal'));
    }

    public function test_invoice_summary_reports_confirmed_allocation_total_without_claiming_invoice_balance(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $allocation = $service->propose($actor, $payment->id, 'invoice', '80.00', 'RON', 'customer_invoice', $invoice->id, 'Partial payment');
        $service->confirm($allocation->id, $actor);

        $summary = $service->invoiceSummary('customer_invoice', $invoice->id);
        $this->assertSame('allocation_total_only', $summary['status']);
        $this->assertSame('80.00', $summary['allocated_total']);
        $this->assertSame(1, $summary['allocation_count']);
        $this->assertSame('unresolved_invoice_total_semantics', $summary['invoice_balance_validation']);
    }

    public function test_summary_flags_currency_changes_after_a_confirmed_allocation(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $allocation = $service->propose($actor, $payment->id, 'invoice', '20.00', 'RON', 'customer_invoice', $invoice->id, 'Currency drift test');
        $service->confirm($allocation->id, $actor);

        $payment->forceFill(['currency_code' => 'EUR'])->save();
        $this->assertSame('needs_review', $service->paymentSummary($payment->fresh())['status']);

        $invoice->forceFill(['currency_code' => 'EUR'])->save();
        $this->assertSame('needs_review', $service->invoiceSummary('customer_invoice', $invoice->id)['status']);
    }

    public function test_confirming_allocations_cannot_exceed_source_payment_and_overpayment_stays_unallocated(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $first = $service->propose($actor, $payment->id, 'invoice', '100.00', 'RON', 'customer_invoice', $invoice->id, 'Invoice allocation');
        $service->confirm($first->id, $actor);
        $tooLarge = $service->propose($actor, $payment->id, 'fee', '30.00', 'RON', null, null, 'Bank fee proposal');

        try {
            $service->confirm($tooLarge->id, $actor);
            $this->fail('Allocation totals must not exceed the source payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_decimal', $exception->errors());
        }

        $summary = $service->paymentSummary($payment->fresh());
        $this->assertSame('partially_allocated', $summary['status']);
        $this->assertSame('100.00', $summary['total_allocated']);
        $this->assertSame('25.00', $summary['unallocated']);
        $this->assertSame(PaymentAllocation::STATUS_PROPOSED, $tooLarge->fresh()->status);
    }

    public function test_one_payment_can_be_split_across_multiple_invoices(): void
    {
        [$payment, $actor, $firstInvoice] = $this->paymentFixture();
        $secondInvoice = CustomerInvoice::factory()->create(['month' => '2026-02-01', 'currency_code' => 'RON']);
        $service = app(PaymentAllocationService::class);
        $first = $service->propose($actor, $payment->id, 'invoice', '70.00', 'RON', 'customer_invoice', $firstInvoice->id, 'First invoice portion');
        $second = $service->propose($actor, $payment->id, 'invoice', '40.00', 'RON', 'customer_invoice', $secondInvoice->id, 'Second invoice portion');
        $service->confirm($first->id, $actor);
        $service->confirm($second->id, $actor);

        $summary = $service->paymentSummary($payment->fresh());
        $this->assertSame('110.00', $summary['invoice_allocated']);
        $this->assertSame('15.00', $summary['unallocated']);
        $this->assertSame('partially_allocated', $summary['status']);
        $this->assertSame('70.00', $service->invoiceSummary('customer_invoice', $firstInvoice->id)['allocated_total']);
        $this->assertSame('40.00', $service->invoiceSummary('customer_invoice', $secondInvoice->id)['allocated_total']);
    }

    public function test_duplicate_confirmed_allocation_to_same_invoice_is_rejected_until_prior_one_is_reversed(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $first = $service->propose($actor, $payment->id, 'invoice', '30.00', 'RON', 'customer_invoice', $invoice->id, 'First allocation');
        $service->confirm($first->id, $actor);
        $duplicate = $service->propose($actor, $payment->id, 'invoice', '20.00', 'RON', 'customer_invoice', $invoice->id, 'Duplicate allocation attempt');

        try {
            $service->confirm($duplicate->id, $actor);
            $this->fail('Duplicate active allocations to the same invoice must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target_id', $exception->errors());
        }

        $service->reverse($first->id, $actor, 'Replacing the original allocation');
        $service->confirm($duplicate->id, $actor);
        $this->assertSame(PaymentAllocation::STATUS_CONFIRMED, $duplicate->fresh()->status);
    }

    public function test_fee_allocations_are_counted_against_payment_cap_without_invoice_target(): void
    {
        [$payment, $actor] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $fee = $service->propose($actor, $payment->id, 'fee', '5.00', 'RON', null, null, 'Fee shown separately on source statement');
        $service->confirm($fee->id, $actor);

        $summary = $service->paymentSummary($payment->fresh());
        $this->assertSame('5.00', $summary['fees']);
        $this->assertSame('120.00', $summary['unallocated']);
        $this->assertSame('partially_allocated', $summary['status']);
    }

    public function test_rejected_proposal_is_audited_and_does_not_consume_payment_capacity(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $proposal = $service->propose($actor, $payment->id, 'invoice', '80.00', 'RON', 'customer_invoice', $invoice->id, 'Proposed but not approved');
        $rejected = $service->reject($proposal->id, $actor, 'Evidence does not support this invoice');

        $this->assertSame(PaymentAllocation::STATUS_REJECTED, $rejected->status);
        $this->assertSame('unmatched', $service->paymentSummary($payment->fresh())['status']);
        $this->assertDatabaseHas('activity_log', ['event' => 'payment_allocation_rejected', 'subject_id' => $proposal->id]);
    }

    public function test_reversal_is_audited_and_releases_source_payment_capacity(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $allocation = $service->propose($actor, $payment->id, 'invoice', '125.00', 'RON', 'customer_invoice', $invoice->id, 'Full payment');
        $service->confirm($allocation->id, $actor);
        $reversed = $service->reverse($allocation->id, $actor, 'Original invoice reference was incorrect');

        $this->assertSame(PaymentAllocation::STATUS_REVERSED, $reversed->status);
        $this->assertSame('Original invoice reference was incorrect', $reversed->reversal_reason);
        $this->assertSame('unmatched', $service->paymentSummary($payment->fresh())['status']);
        $this->assertDatabaseHas('activity_log', ['event' => 'payment_allocation_confirmed', 'subject_id' => $allocation->id]);
        $this->assertDatabaseHas('activity_log', ['event' => 'payment_allocation_reversed', 'subject_id' => $allocation->id]);
    }

    public function test_allocation_rejects_currency_mismatch_and_unresolved_invoice_currency(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);

        try {
            $service->propose($actor, $payment->id, 'invoice', '20.00', 'EUR', 'customer_invoice', $invoice->id, 'Currency mismatch');
            $this->fail('Cross-currency allocation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('currency_code', $exception->errors());
        }

        $invoice->forceFill(['currency_code' => null])->save();
        $this->expectException(ValidationException::class);
        $service->propose($actor, $payment->id, 'invoice', '20.00', 'RON', 'customer_invoice', $invoice->id, 'Invoice currency unresolved');
    }

    public function test_confirmation_rechecks_invoice_currency_after_proposal(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $proposal = $service->propose($actor, $payment->id, 'invoice', '10.00', 'RON', 'customer_invoice', $invoice->id, 'Proposed with verified currency');
        $invoice->forceFill(['currency_code' => 'EUR'])->save();

        $this->expectException(ValidationException::class);
        $service->confirm($proposal->id, $actor);
    }

    public function test_vendor_invoice_allocation_requires_money_out_classification(): void
    {
        [$payment, $actor] = $this->paymentFixture('money_in');
        $vendorInvoice = VendorInvoice::factory()->create(['month' => '2026-01-01', 'currency_code' => 'RON']);

        $this->expectException(ValidationException::class);
        app(PaymentAllocationService::class)->propose($actor, $payment->id, 'invoice', '10.00', 'RON', 'vendor_invoice', $vendorInvoice->id, 'Wrong direction test');
    }

    public function test_allocation_rejects_invalid_target_and_nonpositive_amount(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);

        try {
            $service->propose($actor, $payment->id, 'invoice', '0.00', 'RON', 'customer_invoice', $invoice->id, 'Zero allocation');
            $this->fail('Zero allocation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_decimal', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $service->propose($actor, $payment->id, 'invoice', '10.00', 'RON', 'unknown_invoice', 1, 'Invalid target type');
    }

    public function test_allocation_rejects_payment_without_canonical_amount_or_currency(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $payment->forceFill(['amount_decimal' => null])->save();

        $this->expectException(ValidationException::class);
        app(PaymentAllocationService::class)->propose($actor, $payment->id, 'invoice', '10.00', 'RON', 'customer_invoice', $invoice->id, 'Missing canonical amount');
    }

    public function test_reversal_requires_a_reason_and_only_confirmed_allocations_can_be_reversed(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        $service = app(PaymentAllocationService::class);
        $allocation = $service->propose($actor, $payment->id, 'invoice', '20.00', 'RON', 'customer_invoice', $invoice->id, 'Proposal');

        try {
            $service->reverse($allocation->id, $actor, '   ');
            $this->fail('A reversal reason is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $service->reverse($allocation->id, $actor, 'Not confirmed yet');
    }

    public function test_reconciliation_audit_detects_overallocated_source_payment_without_mutating_it(): void
    {
        [$payment, $actor, $invoice] = $this->paymentFixture();
        foreach (['100.00', '50.00'] as $amount) {
            PaymentAllocation::query()->create([
                'transaction_id' => $payment->id,
                'customer_invoice_id' => $invoice->id,
                'allocation_kind' => 'invoice',
                'amount_decimal' => $amount,
                'currency_code' => 'RON',
                'status' => 'confirmed',
                'reason' => 'Synthetic audit fixture',
                'created_by' => $actor->id,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);
        }

        Artisan::call('finance:reconciliation-audit');
        $output = Artisan::output();

        $this->assertStringContainsString('confirmed allocation total exceeds source payment', $output);
        $this->assertSame('125.00', (string) $payment->fresh()->amount_decimal);
        $this->assertSame('overallocated', app(PaymentAllocationService::class)->paymentSummary($payment->fresh())['status']);
    }

    private function paymentFixture(string $direction = 'money_in'): array
    {
        $account = FinancialAccount::factory()->create(['currency' => 'RON']);
        $actor = User::factory()->create();
        $customerInvoice = CustomerInvoice::factory()->create(['month' => '2026-01-01', 'currency_code' => 'RON']);
        $vendorInvoice = VendorInvoice::factory()->create(['month' => '2026-01-01', 'currency_code' => 'RON']);
        $tax = Tax::factory()->create(['month' => '2026-01-01']);
        $payment = Transaction::factory()->create([
            'financial_account_id' => $account->id,
            'customer_invoice_id' => $customerInvoice->id,
            'vendor_invoice_id' => $vendorInvoice->id,
            'tax_id' => $tax->id,
            'amount' => 125,
            'amount_decimal' => '125.00',
            'currency_code' => 'RON',
            'transaction_type_code' => $direction,
        ]);

        return [$payment, $actor, $customerInvoice];
    }
}
