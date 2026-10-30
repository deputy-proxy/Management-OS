<?php

namespace Tests\Feature;

use App\Filament\Pages\ReceivablesPayablesDashboard;
use App\Models\CustomerInvoice;
use App\Models\FinancialAccount;
use App\Models\IssuedInvoice;
use App\Models\LegalEntity;
use App\Models\PaymentAllocation;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\Finance\ReceivablesPayablesData;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ReceivablesPayablesDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_partial_allocations_and_reversals_reconcile_to_the_issued_invoice_without_mutating_its_state(): void
    {
        $actor = User::factory()->create();
        [$issued, $legacy] = $this->issuedInvoice('100.00', '2026-10-20');
        $first = $this->payment($legacy, $actor, '100.00', 'money_in');
        $second = $this->payment($legacy, $actor, '100.00', 'money_in');
        $reversedPayment = $this->payment($legacy, $actor, '100.00', 'money_in');
        $pendingPayment = $this->payment($legacy, $actor, '100.00', 'money_in');

        $this->allocation($first, $legacy, null, $actor, '30.00', 'confirmed');
        $this->allocation($second, $legacy, null, $actor, '20.00', 'confirmed');
        $this->allocation($reversedPayment, $legacy, null, $actor, '10.00', 'reversed');
        $this->allocation($pendingPayment, $legacy, null, $actor, '15.00', 'proposed');

        $report = app(ReceivablesPayablesData::class)->report(CarbonImmutable::parse('2026-10-30', 'Europe/Bucharest'));
        $row = collect($report['receivables'])->firstWhere('source_id', $issued->id);

        $this->assertSame('100.00', $row['invoice_total']);
        $this->assertSame('50.00', $row['confirmed_allocated']);
        $this->assertSame('50.00', $row['outstanding_amount']);
        $this->assertSame('0.00', $row['credit_amount']);
        $this->assertSame('partially_allocated', $row['balance_status']);
        $this->assertSame('overdue_1_30_days', $row['aging_bucket']);
        $this->assertSame(2, $row['allocation_count']);
        $this->assertSame(1, $row['pending_allocation_count']);
        $this->assertSame(1, $row['reversed_allocation_count']);
        $this->assertSame('issued', $issued->fresh()->document_status);
        $this->assertSame('RON', $legacy->fresh()->currency_code);
        $this->assertSame('50.00', $report['receivable_totals']['RON']['outstanding']);
    }

    public function test_overpayment_is_reported_as_a_credit_balance_and_never_as_negative_outstanding(): void
    {
        $actor = User::factory()->create();
        [$issued, $legacy] = $this->issuedInvoice('50.00', '2026-10-01');
        $payment = $this->payment($legacy, $actor, '100.00', 'money_in');
        $this->allocation($payment, $legacy, null, $actor, '65.00', 'confirmed');

        $row = collect(app(ReceivablesPayablesData::class)
            ->report(CarbonImmutable::parse('2026-10-30', 'Europe/Bucharest'))['receivables'])
            ->firstWhere('source_id', $issued->id);

        $this->assertSame('65.00', $row['confirmed_allocated']);
        $this->assertSame('0.00', $row['outstanding_amount']);
        $this->assertSame('15.00', $row['credit_amount']);
        $this->assertSame('credit_balance', $row['balance_status']);
        $this->assertSame('not_applicable', $row['aging_bucket']);
    }

    public function test_duplicate_active_allocations_from_one_payment_are_flagged_and_excluded(): void
    {
        $actor = User::factory()->create();
        [$issued, $legacy] = $this->issuedInvoice('100.00', '2026-10-01');
        $payment = $this->payment($legacy, $actor, '100.00', 'money_in');
        $this->allocation($payment, $legacy, null, $actor, '30.00', 'confirmed');
        $this->allocation($payment, $legacy, null, $actor, '20.00', 'confirmed');

        $row = collect(app(ReceivablesPayablesData::class)
            ->report(CarbonImmutable::parse('2026-10-30', 'Europe/Bucharest'))['receivables'])
            ->firstWhere('source_id', $issued->id);

        $this->assertSame('needs_review', $row['balance_status']);
        $this->assertNull($row['outstanding_amount']);
        $this->assertContains('duplicate_confirmed_payment_allocation', $row['reason_codes']);
    }

    public function test_source_payment_currency_direction_and_overallocation_are_validated_before_subtraction(): void
    {
        $actor = User::factory()->create();
        [$issued, $legacy] = $this->issuedInvoice('100.00', '2026-10-01');
        $payment = $this->payment($legacy, $actor, '40.00', 'money_out');
        $this->allocation($payment, $legacy, null, $actor, '30.00', 'confirmed');

        $row = collect(app(ReceivablesPayablesData::class)
            ->report(CarbonImmutable::parse('2026-10-30', 'Europe/Bucharest'))['receivables'])
            ->firstWhere('source_id', $issued->id);

        $this->assertSame('needs_review', $row['balance_status']);
        $this->assertContains('source_payment_direction_unresolved', $row['reason_codes']);
    }

    public function test_allocation_currency_mismatch_and_source_payment_overallocation_are_unresolved(): void
    {
        $actor = User::factory()->create();
        [$currencyInvoice, $currencyLegacy] = $this->issuedInvoice('100.00', '2026-10-01');
        $wrongCurrencyPayment = $this->payment($currencyLegacy, $actor, '50.00', 'money_in', null, 'EUR');
        $this->allocation($wrongCurrencyPayment, $currencyLegacy, null, $actor, '20.00', 'confirmed', 'EUR');

        [$overallocatedInvoice, $overallocatedLegacy] = $this->issuedInvoice('100.00', '2026-10-01');
        $overallocatedPayment = $this->payment($overallocatedLegacy, $actor, '40.00', 'money_in');
        $this->allocation($overallocatedPayment, $overallocatedLegacy, null, $actor, '30.00', 'confirmed');
        PaymentAllocation::query()->create([
            'transaction_id' => $overallocatedPayment->id,
            'allocation_kind' => PaymentAllocation::KIND_FEE,
            'amount_decimal' => '20.00',
            'currency_code' => 'RON',
            'status' => PaymentAllocation::STATUS_CONFIRMED,
            'reason' => 'Synthetic over-allocation fixture',
            'created_by' => $actor->id,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
        ]);

        $rows = collect(app(ReceivablesPayablesData::class)
            ->report(CarbonImmutable::parse('2026-10-30', 'Europe/Bucharest'))['receivables'])
            ->where('source_type', 'issued_customer_invoice')->keyBy('source_id');

        $this->assertSame('needs_review', $rows[$currencyInvoice->id]['balance_status']);
        $this->assertContains('confirmed_allocation_currency_mismatch', $rows[$currencyInvoice->id]['reason_codes']);
        $this->assertSame('needs_review', $rows[$overallocatedInvoice->id]['balance_status']);
        $this->assertContains('source_payment_overallocated', $rows[$overallocatedInvoice->id]['reason_codes']);
    }

    public function test_missing_links_legacy_invoice_records_and_correction_documents_remain_unresolved(): void
    {
        User::factory()->create();
        [$linked, $legacy] = $this->issuedInvoice('100.00', '2026-10-01');
        $missingLink = $this->createIssuedSnapshot(null, 'invoice', 'issued', '70.00', '2026-10-01', '2026-10-15');
        $corrected = $this->createIssuedSnapshot(null, 'invoice', 'corrected', '80.00', '2026-10-01', '2026-10-15', $linked->id);
        $creditNote = $this->createIssuedSnapshot(null, 'credit_note', 'issued', '20.00', '2026-10-15', '2026-10-20', $linked->id);
        $legacyOnly = CustomerInvoice::factory()->create(['currency_code' => 'RON']);

        $rows = app(ReceivablesPayablesData::class)
            ->report(CarbonImmutable::parse('2026-10-30', 'Europe/Bucharest'))['receivables'];

        $issuedRows = collect($rows)->where('source_type', 'issued_customer_invoice')->keyBy('source_id');
        $legacyRows = collect($rows)->where('source_type', 'legacy_customer_invoice')->keyBy('source_id');
        $this->assertContains('missing_legacy_payment_allocation_link', $issuedRows[$missingLink->id]['reason_codes']);
        $this->assertContains('issued_invoice_has_been_corrected', $issuedRows[$corrected->id]['reason_codes']);
        $this->assertContains('credit_or_debit_note_requires_approved_netting_rule', $issuedRows[$creditNote->id]['reason_codes']);
        $this->assertSame('needs_review', $legacyRows[$legacyOnly->id]['balance_status']);
        $this->assertNull($legacyRows[$legacyOnly->id]['invoice_total']);
    }

    public function test_aging_boundaries_are_based_on_due_date_and_the_selected_reporting_date(): void
    {
        $cases = [
            '2026-10-31' => 'not_due',
            '2026-10-30' => 'due_today',
            '2026-10-29' => 'overdue_1_30_days',
            '2026-09-30' => 'overdue_1_30_days',
            '2026-09-29' => 'overdue_31_60_days',
            '2026-08-31' => 'overdue_31_60_days',
            '2026-08-30' => 'overdue_61_90_days',
            '2026-08-01' => 'overdue_61_90_days',
            '2026-07-31' => 'overdue_over_90_days',
        ];

        $expected = [];
        foreach ($cases as $dueDate => $bucket) {
            [$issued] = $this->issuedInvoice('100.00', $dueDate);
            $expected[$issued->id] = $bucket;
        }
        [$missingDueDate] = $this->issuedInvoice('100.00', null);
        $expected[$missingDueDate->id] = 'due_date_unknown';

        $rows = collect(app(ReceivablesPayablesData::class)
            ->report(CarbonImmutable::parse('2026-10-30', 'Europe/Bucharest'))['receivables'])->keyBy('source_id');

        foreach ($expected as $id => $bucket) {
            $this->assertSame($bucket, $rows[$id]['aging_bucket'], 'Unexpected aging bucket for issued invoice '.$id);
        }
    }

    public function test_vendor_payable_amount_stays_unresolved_while_confirmed_money_out_allocations_remain_visible(): void
    {
        $actor = User::factory()->create();
        $vendorInvoice = VendorInvoice::factory()->create([
            'workflow_status' => 'payable',
            'month' => '2026-10-01',
            'payable_on' => '2026-10-01',
            'currency_code' => 'RON',
            'amount_decimal' => '450.00',
        ]);
        $payment = $this->payment(null, $actor, '100.00', 'money_out', $vendorInvoice);
        $this->allocation($payment, null, $vendorInvoice, $actor, '35.00', 'confirmed');

        $row = collect(app(ReceivablesPayablesData::class)
            ->report(CarbonImmutable::parse('2026-10-30', 'Europe/Bucharest'))['payables'])
            ->firstWhere('source_id', $vendorInvoice->id);

        $this->assertNull($row['payable_amount']);
        $this->assertNull($row['outstanding_amount']);
        $this->assertSame('35.00', $row['confirmed_allocated']);
        $this->assertSame('confirmed_allocations_recorded_not_settlement', $row['payment_state']);
        $this->assertSame('overdue_1_30_days', $row['aging_bucket']);
        $this->assertContains('vendor_payable_total_not_approved', $row['reason_codes']);
    }

    public function test_dashboard_requires_its_dedicated_permission(): void
    {
        $page = new ReceivablesPayablesDashboard;
        $this->assertSame(
            ['receivables-payables.view' => __('Allows viewing receivables and payables reporting')],
            $page->defineGates(),
        );

        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'receivables-payables.view' ? true : null);
        $this->assertTrue(ReceivablesPayablesDashboard::canAccess());
    }

    public function test_dashboard_page_renders_for_a_role_with_its_dedicated_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Receivables report viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => ['receivables-payables.view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(ReceivablesPayablesDashboard::getUrl())
            ->assertSuccessful()
            ->assertSee('Receivables and payables')
            ->assertSee('Vendor invoice records');
    }

    public function test_dashboard_denies_access_without_its_dedicated_permission(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'receivables-payables.view' ? false : null);

        $this->assertFalse(ReceivablesPayablesDashboard::canAccess());
    }

    /**
     * @return array{0: IssuedInvoice, 1: CustomerInvoice}
     */
    private function issuedInvoice(string $gross, ?string $dueDate, ?int $legacyId = null): array
    {
        $legacy = $legacyId === null
            ? CustomerInvoice::factory()->create(['currency_code' => 'RON'])
            : CustomerInvoice::query()->findOrFail($legacyId);

        return [
            $this->createIssuedSnapshot($legacy->id, 'invoice', 'issued', $gross, '2026-10-01', $dueDate),
            $legacy,
        ];
    }

    private function createIssuedSnapshot(
        ?int $legacyId,
        string $type,
        string $status,
        string $gross,
        string $issueDate,
        ?string $dueDate,
        ?int $correctsId = null,
    ): IssuedInvoice {
        $entity = LegalEntity::query()->firstOrCreate(
            ['tax_identifier' => 'TEST-ARAP-'.LegalEntity::query()->count()],
            [
                'legal_name' => 'Synthetic AR AP Issuer',
                'country_code' => 'RO',
                'tax_identifier_type' => 'CUI',
            ],
        );

        return IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id,
            'legacy_customer_invoice_id' => $legacyId,
            'document_type' => $type,
            'document_status' => $status,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'currency_code' => 'RON',
            'gross_total' => $gross,
            'net_total' => $gross,
            'tax_total' => '0.00',
            'invoice_number' => 'ARAP-'.uniqid(),
            'corrects_issued_invoice_id' => $correctsId,
            'issued_snapshot_hash' => str_repeat('a', 64),
        ]);
    }

    private function payment(
        ?CustomerInvoice $customerInvoice,
        User $actor,
        string $amount,
        string $direction,
        ?VendorInvoice $vendorInvoice = null,
        string $currency = 'RON',
    ): Transaction {
        return Transaction::factory()->create([
            'financial_account_id' => FinancialAccount::factory()->create(['currency' => $currency])->id,
            'vendor_invoice_id' => $vendorInvoice?->id,
            'customer_invoice_id' => $customerInvoice?->id,
            'tax_id' => Tax::factory()->create()->id,
            'transaction_type' => $direction === 'money_in' ? 'Customer' : 'Vendor',
            'transaction_type_code' => $direction,
            'amount_decimal' => $amount,
            'currency_code' => $currency,
            'timestamp' => '2026-10-15 12:00:00',
        ]);
    }

    private function allocation(
        Transaction $payment,
        ?CustomerInvoice $customerInvoice,
        ?VendorInvoice $vendorInvoice,
        User $actor,
        string $amount,
        string $status,
        string $currency = 'RON',
    ): PaymentAllocation {
        return PaymentAllocation::query()->create([
            'transaction_id' => $payment->id,
            'customer_invoice_id' => $customerInvoice?->id,
            'vendor_invoice_id' => $vendorInvoice?->id,
            'allocation_kind' => PaymentAllocation::KIND_INVOICE,
            'amount_decimal' => $amount,
            'currency_code' => $currency,
            'status' => $status,
            'reason' => 'Synthetic receivable/payable fixture',
            'created_by' => $actor->id,
            'confirmed_by' => $status === 'confirmed' || $status === 'reversed' ? $actor->id : null,
            'confirmed_at' => $status === 'confirmed' || $status === 'reversed' ? now() : null,
            'reversed_by' => $status === 'reversed' ? $actor->id : null,
            'reversed_at' => $status === 'reversed' ? now() : null,
            'reversal_reason' => $status === 'reversed' ? 'Synthetic reversal fixture' : null,
        ]);
    }
}
