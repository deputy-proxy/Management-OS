<?php

namespace Tests\Feature;

use App\Filament\Pages\CashForecastPlanning;
use App\Models\CustomerInvoice;
use App\Models\FinancialAccount;
use App\Models\IssuedInvoice;
use App\Models\LegalEntity;
use App\Models\PaymentAllocation;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\CashForecastService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class CashForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        foreach (['cash-forecast.view', 'cash-forecast.create', 'cash-forecast.update', 'cash-forecast.approve'] as $ability) {
            Gate::define($ability, fn (): bool => true);
        }
    }

    public function test_forecast_keeps_actual_cash_movements_separate_from_projected_scenario_entries(): void
    {
        $scenario = app(CashForecastService::class)->create($this->payload([
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-03',
            'opening_balance' => '100.00',
            'entries' => [
                $this->entry('inflow', 'Expected customer receipt', '2026-11-02', '30.00'),
                $this->entry('outflow', 'Planned supplier payment', '2026-11-03', '20.00'),
            ],
        ]));
        $this->transaction('money_in', '50.00', '2026-11-01 10:00:00');
        $this->transaction('money_out', '10.00', '2026-11-01 12:00:00');
        $this->transaction('money_in', '99.00', '2026-11-01 13:00:00', 'EUR');

        $report = app(CashForecastService::class)->report($scenario, CarbonImmutable::parse('2026-11-01', 'Europe/Bucharest'));

        $this->assertSame('50.00', $report['rows'][0]['actual_inflows']);
        $this->assertSame('10.00', $report['rows'][0]['actual_outflows']);
        $this->assertSame('0.00', $report['rows'][0]['projected_inflows']);
        $this->assertSame('30.00', $report['rows'][1]['projected_inflows']);
        $this->assertSame('20.00', $report['rows'][2]['projected_outflows']);
        $this->assertSame('150.00', $report['closing_balance']);
        $this->assertSame('50.00', $report['totals']['actual_inflows']);
        $this->assertSame('30.00', $report['totals']['projected_inflows']);
        $this->assertSame(1, $report['sources']['other_currency_actual_transaction_count']);
    }

    public function test_approved_scenarios_are_immutable_and_new_versions_copy_prior_assumptions(): void
    {
        $service = app(CashForecastService::class);
        $scenario = $service->create($this->payload());
        $approved = $service->approve($scenario);

        try {
            $service->updateDraft($approved, $this->payload(['opening_balance' => '999.00']));
            $this->fail('Approved scenarios must be immutable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $next = $service->createVersion($approved, ['opening_balance' => '150.00']);
        $this->assertSame(2, $next->version);
        $this->assertSame('150.00', $next->opening_balance);
        $this->assertSame('100.00', $approved->fresh()->opening_balance);
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('draft', $next->status);
        $this->assertSame(1, $next->entries()->count());
    }

    public function test_empty_manual_assumption_scenario_can_be_approved_without_inventing_cashflows(): void
    {
        $service = app(CashForecastService::class);
        $scenario = $service->create($this->payload(['entries' => []]));
        $approved = $service->approve($scenario);
        $report = $service->report($approved, CarbonImmutable::parse('2026-11-01', 'Europe/Bucharest'));

        $this->assertSame('approved', $approved->status);
        $this->assertSame(0, $report['manual_assumption_count']);
        $this->assertSame('100.00', $report['closing_balance']);
        $this->assertSame('0.00', $report['totals']['projected_inflows']);
        $this->assertSame('0.00', $report['totals']['projected_outflows']);
    }

    public function test_invalid_dates_currency_and_manual_entry_amounts_are_rejected(): void
    {
        $service = app(CashForecastService::class);
        $invalidPayloads = [
            ['period_start' => '2026-02-30'],
            ['period_start' => '2027-01-01', 'period_end' => '2026-01-01'],
            ['currency_code' => 'EU'],
            ['opening_balance' => ''],
            ['entries' => [$this->entry('outflow', 'Supplier', '2027-02-01', '0.00')]],
            ['entries' => [$this->entry('outflow', 'Supplier', '2027-02-01', '10.00', 'EUR')]],
        ];
        foreach ($invalidPayloads as $overrides) {
            try {
                $service->create($this->payload($overrides));
                $this->fail('Invalid forecast data must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('cash_forecasts', 0);
            }
        }
    }

    public function test_scenario_and_entry_models_reject_mutation_after_approval(): void
    {
        $service = app(CashForecastService::class);
        $scenario = $service->approve($service->create($this->payload()));
        $entry = $scenario->entries->first();

        try {
            $entry->update(['amount' => '999.00']);
            $this->fail('Approved entries must be immutable.');
        } catch (LogicException) {
            $this->assertSame('250.00', $entry->fresh()->amount);
        }
    }

    public function test_partial_invoice_payment_is_counted_once_as_actual_and_only_the_outstanding_balance_is_projected(): void
    {
        $actor = User::factory()->create();
        [$issued, $legacy] = $this->issuedInvoice('100.00', '2026-11-02');
        $payment = $this->sourcePayment($legacy, '30.00', '2026-10-31 12:00:00');
        $allocation = $this->invoiceAllocation($payment, $legacy, $actor, '30.00');
        DB::table('payment_allocations')->where('id', $allocation->id)->update([
            'created_at' => '2026-10-31 12:00:00', 'confirmed_at' => '2026-10-31 12:05:00',
        ]);

        $scenario = app(CashForecastService::class)->create($this->payload([
            'period_start' => '2026-10-30', 'period_end' => '2026-11-03',
            'entries' => [array_replace($this->entry('inflow', 'Duplicate manual invoice assumption', '2026-11-02', '70.00'), ['source_reference' => $issued->invoice_number])],
        ]));
        $report = app(CashForecastService::class)->report($scenario, CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'));
        $paymentDay = collect($report['rows'])->firstWhere('date', '2026-10-31');
        $dueDay = collect($report['rows'])->firstWhere('date', '2026-11-02');

        $this->assertSame('30.00', $paymentDay['actual_inflows']);
        $this->assertSame('70.00', $dueDay['projected_inflows']);
        $this->assertSame('70.00', $report['totals']['projected_inflows']);
        $this->assertSame(1, $report['sources']['supported_receivable_count']);
        $this->assertSame(1, $report['sources']['duplicate_manual_assumption_count']);
        $this->assertSame('200.00', $report['closing_balance']);
        $this->assertSame('issued', $issued->fresh()->document_status);
    }

    public function test_unmatched_invoice_link_withholds_the_receivable_projection_until_payment_allocation_is_confirmed(): void
    {
        [, $legacy] = $this->issuedInvoice('100.00', '2026-11-02');
        $this->sourcePayment($legacy, '30.00', '2026-10-31 12:00:00');

        $scenario = app(CashForecastService::class)->create($this->payload([
            'period_start' => '2026-10-30', 'period_end' => '2026-11-03', 'entries' => [],
        ]));
        $report = app(CashForecastService::class)->report($scenario, CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'));
        $paymentDay = collect($report['rows'])->firstWhere('date', '2026-10-31');
        $dueDay = collect($report['rows'])->firstWhere('date', '2026-11-02');

        $this->assertSame('30.00', $paymentDay['actual_inflows']);
        $this->assertSame('0.00', $dueDay['projected_inflows']);
        $this->assertSame(1, $report['sources']['unresolved_receivable_payment_links']);
        $this->assertSame('130.00', $report['closing_balance']);
    }

    public function test_duplicate_confirmed_allocations_withhold_receivable_projection_without_duplicating_actual_cash(): void
    {
        $actor = User::factory()->create();
        [, $legacy] = $this->issuedInvoice('100.00', '2026-11-02');
        $payment = $this->sourcePayment($legacy, '30.00', '2026-10-31 12:00:00');
        foreach (['10.00', '20.00'] as $amount) {
            $allocation = $this->invoiceAllocation($payment, $legacy, $actor, $amount);
            DB::table('payment_allocations')->where('id', $allocation->id)->update([
                'created_at' => '2026-10-31 12:00:00', 'confirmed_at' => '2026-10-31 12:05:00',
            ]);
        }

        $scenario = app(CashForecastService::class)->create($this->payload([
            'period_start' => '2026-10-30', 'period_end' => '2026-11-03', 'entries' => [],
        ]));
        $report = app(CashForecastService::class)->report($scenario, CarbonImmutable::parse('2026-10-31', 'Europe/Bucharest'));
        $paymentDay = collect($report['rows'])->firstWhere('date', '2026-10-31');
        $dueDay = collect($report['rows'])->firstWhere('date', '2026-11-02');

        $this->assertSame('30.00', $paymentDay['actual_inflows']);
        $this->assertSame('0.00', $dueDay['projected_inflows']);
        $this->assertSame(0, $report['sources']['supported_receivable_count']);
        $this->assertSame('130.00', $report['closing_balance']);
    }

    public function test_scenario_version_can_shift_cashflow_timing_without_rewriting_the_prior_version(): void
    {
        $service = app(CashForecastService::class);
        $scenario = $service->create($this->payload([
            'period_start' => '2026-11-01', 'period_end' => '2026-11-03',
            'entries' => [$this->entry('inflow', 'Customer receipt', '2026-11-02', '30.00')],
        ]));
        $approved = $service->approve($scenario);
        $shifted = $service->createVersion($approved, [
            'entries' => [$this->entry('inflow', 'Customer receipt delayed', '2026-11-03', '30.00')],
        ]);
        $asOf = CarbonImmutable::parse('2026-11-01', 'Europe/Bucharest');
        $originalReport = $service->report($approved, $asOf);
        $shiftedReport = $service->report($shifted, $asOf);

        $this->assertSame('30.00', collect($originalReport['rows'])->firstWhere('date', '2026-11-02')['projected_inflows']);
        $this->assertSame('0.00', collect($shiftedReport['rows'])->firstWhere('date', '2026-11-02')['projected_inflows']);
        $this->assertSame('30.00', collect($shiftedReport['rows'])->firstWhere('date', '2026-11-03')['projected_inflows']);
        $this->assertSame('2026-11-02', $approved->fresh()->entries->first()->expected_on->toDateString());
        $this->assertSame(2, $shifted->version);
    }

    public function test_missing_actual_amount_and_currency_are_reported_without_imputation(): void
    {
        $scenario = app(CashForecastService::class)->create($this->payload(['entries' => []]));
        Transaction::factory()->create([
            'financial_account_id' => FinancialAccount::factory()->create()->id,
            'vendor_invoice_id' => null, 'customer_invoice_id' => null, 'tax_id' => null,
            'transaction_type' => 'Unresolved cash movement', 'transaction_type_code' => 'money_in',
            'amount' => '0.00', 'amount_decimal' => null, 'currency_code' => null,
            'timestamp' => '2026-11-01 12:00:00',
        ]);

        $report = app(CashForecastService::class)->report($scenario, CarbonImmutable::parse('2026-11-01', 'Europe/Bucharest'));

        $this->assertSame('100.00', $report['closing_balance']);
        $this->assertSame('0.00', $report['totals']['actual_inflows']);
        $this->assertSame(1, $report['sources']['unknown_actual_transaction_count']);
    }

    public function test_vendor_payable_amount_is_never_inferred_from_legacy_invoice_amount_fields(): void
    {
        VendorInvoice::factory()->create([
            'workflow_status' => 'payable', 'month' => '2026-11-01', 'payable_on' => '2026-11-02',
            'currency_code' => 'RON', 'amount_decimal' => '450.00',
        ]);
        $scenario = app(CashForecastService::class)->create($this->payload([
            'period_start' => '2026-11-01', 'period_end' => '2026-11-03', 'entries' => [],
        ]));

        $report = app(CashForecastService::class)->report($scenario, CarbonImmutable::parse('2026-11-01', 'Europe/Bucharest'));
        $dueDay = collect($report['rows'])->firstWhere('date', '2026-11-02');

        $this->assertSame('0.00', $dueDay['projected_outflows']);
        $this->assertSame(1, $report['sources']['payables_unavailable_count']);
        $this->assertSame('100.00', $report['closing_balance']);
    }

    public function test_generated_forecast_runs_are_immutable_reproducible_snapshots(): void
    {
        $scenario = app(CashForecastService::class)->create($this->payload(['entries' => []]));
        $transaction = $this->transaction('money_in', '50.00', '2026-11-01 10:00:00');
        $service = app(CashForecastService::class);
        $run = $service->generateRun($scenario, CarbonImmutable::parse('2026-11-01', 'Europe/Bucharest'));

        $this->assertSame('50.00', $run->report_snapshot['totals']['actual_inflows']);
        $this->assertSame('2026-11-01', $run->as_of->toDateString());
        $this->assertSame([$transaction->id], $run->report_snapshot['rows'][0]['actual_transaction_ids']);

        DB::table('transactions')->where('id', $transaction->id)->update(['amount_decimal' => '90.00']);
        $freshReport = $service->report($scenario, CarbonImmutable::parse('2026-11-01', 'Europe/Bucharest'));
        $this->assertSame('90.00', $freshReport['totals']['actual_inflows']);
        $this->assertSame('50.00', $run->fresh()->report_snapshot['totals']['actual_inflows']);

        try {
            $run->update(['report_snapshot' => []]);
            $this->fail('Forecast run snapshots must be immutable.');
        } catch (LogicException) {
            $this->assertSame('50.00', $run->fresh()->report_snapshot['totals']['actual_inflows']);
        }
    }

    public function test_forecast_page_declares_and_enforces_its_view_permission(): void
    {
        $page = new CashForecastPlanning;
        $this->assertSame([
            'cash-forecast.view' => __('Allows viewing cash forecasts and planning scenarios'),
            'cash-forecast.create' => __('Allows creating cash forecasts'),
            'cash-forecast.update' => __('Allows editing drafts and creating forecast versions'),
            'cash-forecast.approve' => __('Allows approving forecast scenarios'),
        ], $page->defineGates());

        Gate::before(fn ($user, string $ability): ?bool => $ability === 'cash-forecast.view' ? true : null);
        $this->assertTrue(CashForecastPlanning::canAccess());
    }

    public function test_forecast_page_denies_access_without_its_dedicated_view_permission(): void
    {
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'cash-forecast.view' ? false : null);
        $this->assertFalse(CashForecastPlanning::canAccess());
    }

    public function test_forecast_page_renders_for_a_role_with_its_dedicated_view_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Cash forecast viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => ['cash-forecast.view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)->get(CashForecastPlanning::getUrl())
            ->assertSuccessful()
            ->assertSee('Cash Forecast')
            ->assertSee('Forecasts are scenarios, not actual balances.');
    }

    public function test_forecast_permissions_are_enforced_by_the_service(): void
    {
        Gate::define('cash-forecast.create', fn (): bool => false);
        $this->expectException(AuthorizationException::class);
        app(CashForecastService::class)->create($this->payload());
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Synthetic cash plan',
            'scenario_name' => 'Base case',
            'period_start' => '2026-11-01',
            'period_end' => '2027-03-31',
            'currency_code' => 'RON',
            'opening_balance' => '100.00',
            'assumptions' => 'Synthetic assumptions only.',
            'entries' => [$this->entry('outflow', 'Planned supplier payment', '2026-12-01', '250.00')],
        ], $overrides);
    }

    /** @return array<string, string> */
    private function entry(string $direction, string $description, string $date, string $amount, string $currency = 'RON'): array
    {
        return [
            'direction' => $direction, 'description' => $description, 'expected_on' => $date,
            'amount' => $amount, 'currency_code' => $currency, 'confidence' => 'medium', 'status' => 'assumed', 'source_reference' => 'SYNTHETIC-'.substr(sha1($description), 0, 10),
        ];
    }

    /** @return array{0: IssuedInvoice, 1: CustomerInvoice} */
    private function issuedInvoice(string $gross, ?string $dueDate): array
    {
        $legacy = CustomerInvoice::factory()->create(['currency_code' => 'RON']);
        $entity = LegalEntity::query()->create([
            'legal_name' => 'Synthetic Cash Forecast Issuer', 'country_code' => 'RO',
            'tax_identifier_type' => 'CUI', 'tax_identifier' => 'CF-'.uniqid(),
        ]);
        $issued = IssuedInvoice::query()->create([
            'legal_entity_id' => $entity->id, 'legacy_customer_invoice_id' => $legacy->id,
            'document_type' => 'invoice', 'document_status' => 'issued',
            'issue_date' => '2026-10-30', 'due_date' => $dueDate, 'currency_code' => 'RON',
            'gross_total' => $gross, 'net_total' => $gross, 'tax_total' => '0.00',
            'invoice_number' => 'CF-'.uniqid(), 'issued_snapshot_hash' => str_repeat('b', 64),
        ]);

        return [$issued, $legacy];
    }

    private function sourcePayment(CustomerInvoice $invoice, string $amount, string $timestamp): Transaction
    {
        return Transaction::factory()->create([
            'financial_account_id' => FinancialAccount::factory()->create(['currency' => 'RON'])->id,
            'vendor_invoice_id' => null, 'customer_invoice_id' => $invoice->id,
            'tax_id' => Tax::factory()->create()->id, 'transaction_type' => 'Customer',
            'transaction_type_code' => 'money_in', 'amount' => $amount, 'amount_decimal' => $amount,
            'currency_code' => 'RON', 'timestamp' => $timestamp,
        ]);
    }

    private function invoiceAllocation(Transaction $payment, CustomerInvoice $invoice, User $actor, string $amount): PaymentAllocation
    {
        return PaymentAllocation::query()->create([
            'transaction_id' => $payment->id, 'customer_invoice_id' => $invoice->id,
            'allocation_kind' => PaymentAllocation::KIND_INVOICE, 'amount_decimal' => $amount,
            'currency_code' => 'RON', 'status' => PaymentAllocation::STATUS_CONFIRMED,
            'reason' => 'Synthetic cash forecast allocation', 'created_by' => $actor->id,
            'confirmed_by' => $actor->id, 'confirmed_at' => now(),
        ]);
    }

    private function transaction(string $direction, string $amount, string $timestamp, string $currency = 'RON'): Transaction
    {
        return Transaction::factory()->create([
            'financial_account_id' => FinancialAccount::factory()->create()->id, 'vendor_invoice_id' => null, 'customer_invoice_id' => null,
            'tax_id' => null, 'transaction_type' => 'Cash movement', 'transaction_type_code' => $direction,
            'amount' => $amount, 'amount_decimal' => $amount, 'currency_code' => $currency, 'timestamp' => $timestamp,
        ]);
    }
}
