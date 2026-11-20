<?php

namespace Tests\Feature;

use App\Mcp\Authorization\McpToolPermissionRegistry;
use App\Mcp\Server\McpServerFactory;
use App\Mcp\Tools\Finance\FinanceMcpCapabilityRegistrar;
use App\Models\Budget;
use App\Models\BudgetActualReportRun;
use App\Models\BudgetVersion;
use App\Models\CashForecast;
use App\Models\CashForecastScenario;
use App\Models\User;
use App\Services\BudgetActualsComparisonService;
use App\Services\ConsolidatedFinancialReportData;
use App\Services\Finance\FinancialReconciliationReportData;
use App\Services\Finance\ReceivablesPayablesData;
use App\Services\FinancialOverviewData;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Mockery;
use Tests\TestCase;

class McpFinanceToolsTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionPath;

    private string $keyPath;

    private User $principal;

    /** @var list<string> */
    private array $scopes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyPath = storage_path('framework/testing/mcp-finance-passport');
        File::ensureDirectoryExists($this->keyPath, 0700, true);
        $private = $this->keyPath.'/oauth-private.key';
        $public = $this->keyPath.'/oauth-public.key';
        if (! is_file($private) || ! is_file($public)) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $this->assertNotFalse($key);
            $this->assertTrue(openssl_pkey_export($key, $privateKey));
            $details = openssl_pkey_get_details($key);
            $this->assertIsArray($details);
            file_put_contents($private, $privateKey);
            file_put_contents($public, $details['key']);
            chmod($private, 0600);
            chmod($public, 0600);
        }
        Passport::loadKeysFrom($this->keyPath);
        $this->sessionPath = storage_path('framework/testing/mcp-finance-sessions');
        config()->set('mcp.session_path', $this->sessionPath);
        File::deleteDirectory($this->sessionPath);
        $this->principal = User::factory()->create(['email' => 'mcp-finance@example.test']);
        config()->set('mcp.ai_principal_email', $this->principal->email);
        $this->scopes = ['mcp:use', ...array_keys((new McpToolPermissionRegistry)->passportScopes())];
        app()->instance(McpServerFactory::class, new McpServerFactory([app(FinanceMcpCapabilityRegistrar::class)]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessionPath);
        parent::tearDown();
    }

    public function test_nine_finance_tools_are_read_only_and_have_closed_schemas(): void
    {
        $this->grant(['finance-dashboard.view']);
        $tools = $this->mcpPost(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new \stdClass], $this->initialize())
            ->assertOk()->json('result.tools');
        $expected = ['get_financial_overview', 'get_transactions', 'get_receivables', 'get_payables', 'get_cash_forecast', 'get_budget_vs_actuals', 'get_profitability_report', 'get_reconciliation_status', 'get_invoice_details'];
        $this->assertEqualsCanonicalizing($expected, array_column($tools, 'name'));
        foreach ($expected as $name) {
            $permission = (new McpToolPermissionRegistry)->forTool($name);
            $this->assertSame('read', $permission->operationType);
            $this->assertFalse($permission->requiresApproval);
        }
        foreach ($tools as $tool) {
            $this->assertFalse($tool['inputSchema']['additionalProperties'] ?? true);
        }
    }

    public function test_financial_overview_marks_legacy_units_and_filters_optional_invoice_sources(): void
    {
        $this->grant(['finance-dashboard.view', 'transaction.index']);
        $service = Mockery::mock(FinancialOverviewData::class);
        $service->shouldReceive('summary')->once()->andReturn([
            'period' => 'November 2026', 'previous_period' => 'October 2026',
            'transactions' => [['currency' => 'RON', 'type' => 'customer', 'current_total' => 125, 'previous_total' => 90, 'current_count' => 2, 'previous_count' => 1]],
            'canonical_cash_movements' => ['rows' => [['currency' => 'RON', 'direction' => 'money_in', 'current_amount' => '125.00', 'previous_amount' => '90.00', 'current_count' => 2, 'previous_count' => 1]], 'excluded_current_count' => 1, 'excluded_previous_count' => 0],
            'transaction_classifications' => [['code' => 'customer_money_in', 'label' => 'Receipts', 'status' => 'classified', 'current_count' => 2, 'previous_count' => 1]],
            'customer_invoice_statuses' => [['status' => 'issued', 'count' => 3]],
            'vendor_invoice_statuses' => [['status' => 'open', 'currency' => 'RON', 'count' => 2]],
        ]);
        $this->app->instance(FinancialOverviewData::class, $service);
        $r = $this->callTool('get_financial_overview', [], $this->initialize())->assertOk()->json('result.structuredContent');
        $this->assertSame('legacy_integer_unverified', $r['legacy_transactions'][0]['amount_unit']);
        $this->assertSame('125.00', $r['canonical_cash_movements']['rows'][0]['current_amount']);
        $this->assertSame([], $r['customer_invoice_statuses']);
        $this->assertSame([], $r['vendor_invoice_statuses']);
        $this->assertSame('unavailable_permission_denied', $r['source_availability']['customer_invoice_statuses']);
    }

    public function test_transactions_are_paginated_and_do_not_expose_source_identifiers_or_party_data(): void
    {
        $this->grant(['transaction.index']);
        $report = [
            'from' => '2026-11-01', 'to' => '2026-11-30', 'timezone' => 'Europe/Bucharest',
            'totals' => ['included' => 1, 'excluded' => 0, 'unknown' => 1],
            'records' => [
                ['source_type' => 'transaction', 'source_id' => 42, 'source_reference' => 'TX-42', 'source_url' => '/private/42', 'entity' => 'Private customer',
                    'source_date' => '2026-11-02', 'direction' => 'money_in', 'category' => 'customer_money_in', 'currency' => 'RON', 'amount' => '25.00',
                    'status' => 'included', 'reason' => null, 'classification_label' => 'Receipts', 'classification_status' => 'classified',
                    'duplicate_status' => 'not_flagged', 'duplicate_count' => 1, 'special_event_status' => 'not_modelled'],
                ['source_type' => 'transaction', 'source_id' => 43, 'source_reference' => 'TX-43', 'source_url' => '/private/43', 'entity' => 'Private party',
                    'source_date' => '2026-11-03', 'direction' => null, 'category' => 'unclassified_legacy', 'currency' => 'Unknown', 'amount' => null,
                    'status' => 'unknown', 'reason' => 'missing_or_invalid_currency', 'classification_label' => 'Unclassified', 'classification_status' => 'unclassified',
                    'duplicate_status' => 'not_flagged', 'duplicate_count' => 1, 'special_event_status' => 'not_modelled'],
            ],
        ];
        $service = Mockery::mock(ConsolidatedFinancialReportData::class);
        $service->shouldReceive('report')->twice()->andReturn($report);
        $this->app->instance(ConsolidatedFinancialReportData::class, $service);
        $session = $this->initialize();
        $first = $this->callTool('get_transactions', ['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'limit' => 1], $session)
            ->assertOk()->json('result.structuredContent');
        $this->assertSame(2, $first['total_count']);
        $this->assertTrue($first['has_more']);
        $this->assertSame('25.00', $first['records'][0]['amount']);
        $this->assertNull($first['records'][0]['account_label']);
        foreach (['source_id', 'source_reference', 'source_url', 'entity'] as $field) {
            $this->assertArrayNotHasKey($field, $first['records'][0]);
        }
        $second = $this->callTool('get_transactions', ['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'limit' => 1, 'offset' => 1], $session)
            ->assertOk()->json('result.structuredContent.records.0');
        $this->assertSame('unknown', $second['amount_status']);
        $this->assertNull($second['amount']);
    }

    public function test_transactions_over_ninety_days_return_unavailable_without_querying_source(): void
    {
        $this->grant(['transaction.index']);
        $service = Mockery::mock(ConsolidatedFinancialReportData::class);
        $service->shouldNotReceive('report');
        $this->app->instance(ConsolidatedFinancialReportData::class, $service);
        $r = $this->callTool('get_transactions', ['start_date' => '2026-01-01', 'end_date' => '2026-06-01'], $this->initialize())
            ->assertOk()->json('result.structuredContent');
        $this->assertSame('unavailable_date_range_exceeds_limit', $r['status']);
        $this->assertSame(90, $r['maximum_days']);
    }

    public function test_cash_forecast_rejects_draft_scenario_without_generating_run(): void
    {
        $this->grant(['cash-forecast.view', 'transaction.index', 'customer_invoice.index', 'vendor_invoice.index']);
        $forecast = CashForecast::query()->create(['name' => 'MCP fixture', 'created_by' => $this->principal->getKey()]);
        $scenario = CashForecastScenario::query()->create([
            'cash_forecast_id' => $forecast->getKey(), 'scenario_name' => 'Draft', 'version' => 1,
            'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'currency_code' => 'RON',
            'opening_balance' => '100.00', 'assumptions' => [], 'status' => CashForecastScenario::STATUS_DRAFT,
            'created_by' => $this->principal->getKey(),
        ]);
        $r = $this->callTool('get_cash_forecast', ['scenario_id' => $scenario->getKey()], $this->initialize())
            ->assertOk()->json('result.structuredContent');
        $this->assertSame('unavailable_scenario_not_approved', $r['availability']);
        $this->assertSame(0, DB::table('cash_forecast_runs')->count());
    }

    public function test_budget_preview_does_not_create_report_run_and_omits_source_ids(): void
    {
        $this->grant(['budget.actuals.view', 'budget.index', 'transaction.index']);
        $budget = Budget::query()->create(['name' => 'MCP budget', 'created_by' => $this->principal->getKey()]);
        $version = BudgetVersion::query()->create([
            'budget_id' => $budget->getKey(), 'version' => 1, 'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'currency_code' => 'RON', 'dimensions' => [], 'status' => BudgetVersion::STATUS_DRAFT, 'created_by' => $this->principal->getKey(),
        ]);
        DB::table('budget_versions')->where('id', $version->getKey())->update(['status' => BudgetVersion::STATUS_APPROVED, 'approved_by' => $this->principal->getKey(), 'approved_at' => now()]);
        $report = [
            'budget_version' => 1, 'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'currency_code' => 'RON',
            'actuals_basis' => 'classified_transaction_cash_movements_v1', 'actuals_basis_label' => 'Classified cash movements only.',
            'rows' => [['code' => 'customer_money_in', 'description' => 'Sales', 'budget_amount' => '100.00', 'actual_amount' => '90.00', 'variance_amount' => '-10.00', 'variance_percent' => '-10.00', 'status' => 'matched', 'source_transaction_ids' => [55]]],
            'excluded' => [['reason' => 'Currency differs from selected version.', 'source_id' => 99]], 'excluded_known_amount' => '20.00',
            'excluded_known_amount_count' => 1, 'generated_at' => '2026-11-20T12:00:00+02:00',
        ];
        $service = Mockery::mock(BudgetActualsComparisonService::class);
        $service->shouldReceive('compare')->once()->withArgs(fn ($version, ...$args): bool => $version->is($version) && $args === [null, null, null, [], false, false])->andReturn($report);
        $this->app->instance(BudgetActualsComparisonService::class, $service);
        $before = BudgetActualReportRun::query()->count();
        $r = $this->callTool('get_budget_vs_actuals', ['budget_version_id' => $version->getKey()], $this->initialize())
            ->assertOk()->json('result.structuredContent');
        $this->assertSame('90.00', $r['rows'][0]['actual_amount']);
        $this->assertArrayNotHasKey('source_transaction_ids', $r['rows'][0]);
        $this->assertSame($before, BudgetActualReportRun::query()->count());
        $this->assertArrayNotHasKey('source_id', $r['excluded_reason_counts'][0]);
    }

    public function test_receivables_and_payables_filter_party_identity_and_unapproved_amounts(): void
    {
        $this->grant(['receivables-payables.view', 'customer_invoice.index', 'vendor_invoice.index']);
        $report = [
            'as_of' => '2026-11-20', 'timezone' => 'Europe/Bucharest', 'generated_at' => '2026-11-20T12:00:00+02:00',
            'summary' => ['supported_receivables' => 1, 'unresolved_receivables' => 1],
            'receivable_totals' => ['RON' => ['invoice_total' => '100.00', 'confirmed_allocated' => '20.00', 'outstanding' => '80.00', 'credit' => '0.00', 'invoice_count' => 1]],
            'receivables' => [['source_type' => 'issued_customer_invoice', 'source_id' => 10, 'source_reference' => 'INV-10', 'entity' => 'Private customer',
                'document_date' => '2026-11-01', 'due_date' => '2026-11-30', 'currency_code' => 'RON', 'invoice_total' => '100.00',
                'confirmed_allocated' => '20.00', 'outstanding_amount' => '80.00', 'credit_amount' => '0.00', 'balance_status' => 'supported',
                'payment_state' => 'partially_paid', 'aging_bucket' => 'current', 'days_overdue' => 0, 'allocation_count' => 1,
                'pending_allocation_count' => 0, 'reversed_allocation_count' => 0, 'reason_codes' => [], 'document_status' => 'issued',
                'source_status' => 'supported', 'allocation_history' => [['transaction_id' => 1]]]],
            'payables' => [['source_type' => 'vendor_invoice', 'source_id' => 20, 'source_reference' => 'V-20', 'entity' => 'Private vendor',
                'document_date' => '2026-11-01', 'due_date' => '2026-11-30', 'currency_code' => 'RON', 'invoice_total' => null,
                'confirmed_allocated' => '50.00', 'outstanding_amount' => null, 'credit_amount' => null, 'balance_status' => 'unsupported',
                'payment_state' => 'partially_allocated', 'aging_bucket' => 'current', 'days_overdue' => 0, 'allocation_count' => 1,
                'pending_allocation_count' => 0, 'reversed_allocation_count' => 0, 'reason_codes' => ['vendor_payable_total_not_approved'],
                'document_status' => null, 'source_status' => 'unresolved', 'allocation_history' => []]],
        ];
        $service = Mockery::mock(ReceivablesPayablesData::class);
        $service->shouldReceive('report')->twice()->andReturn($report);
        $this->app->instance(ReceivablesPayablesData::class, $service);
        $session = $this->initialize();
        $receivables = $this->callTool('get_receivables', [], $session)->assertOk()->json('result.structuredContent');
        $this->assertNull($receivables['receivables'][0]['confirmed_allocated']);
        $this->assertNull($receivables['totals_by_currency'][0]['confirmed_allocated']);
        $this->assertArrayNotHasKey('entity', $receivables['receivables'][0]);
        $this->assertArrayNotHasKey('source_id', $receivables['receivables'][0]);
        $this->assertArrayNotHasKey('allocation_history', $receivables['receivables'][0]);
        $payables = $this->callTool('get_payables', [], $session)->assertOk()->json('result.structuredContent');
        $this->assertSame('unavailable_vendor_payable_total_not_approved', $payables['amount_availability']);
        $this->assertNull($payables['payables'][0]['invoice_total']);
        $this->assertSame('unavailable_permission_denied', $payables['payables'][0]['payment_state']);
        $this->assertArrayNotHasKey('entity', $payables['payables'][0]);
    }

    public function test_reconciliation_candidate_details_are_hidden_without_transaction_permission(): void
    {
        $this->grant(['financial-reconciliation.view']);
        $service = Mockery::mock(FinancialReconciliationReportData::class);
        $service->shouldReceive('report')->once()->andReturn([
            'from' => '2026-11-01', 'to' => '2026-11-30', 'generated_at' => '2026-11-30 12:00:00',
            'summary' => ['statement_rows' => 1, 'exact_match' => 1, 'partial_candidate' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'rejected' => 0, 'unreviewed' => 0, 'exact_candidates' => 1, 'near_candidates' => 0, 'partial_candidates' => 0],
            'totals_by_currency' => [['currency' => 'RON', 'direction' => 'money_in', 'statement_rows' => 1, 'statement_amount' => '25.00', 'exact_match_amount' => '25.00', 'partial_candidate_amount' => '0.00', 'unmatched_amount' => '0.00', 'ambiguous_amount' => '0.00', 'unreviewed_amount' => '0.00', 'rejected_amount' => '0.00']],
            'rows' => [['date' => '2026-11-03', 'direction' => 'money_in', 'amount' => '25.00', 'currency' => 'RON', 'account' => 'Private account', 'status' => 'exact_match', 'review_reasons' => ['matched'], 'candidate_status' => 'exact_match', 'candidate_count' => 1, 'candidates' => [['date' => '2026-11-03', 'amount' => '25.00', 'currency' => 'RON', 'direction' => 'money_in', 'match_type' => 'exact', 'allocation_counts' => ['confirmed' => 1], 'shared_candidate_count' => 1]]]],
            'currencies' => ['RON'], 'accounts' => [['id' => 7, 'title' => 'Private account']],
        ]);
        $this->app->instance(FinancialReconciliationReportData::class, $service);
        $r = $this->callTool('get_reconciliation_status', ['start_date' => '2026-11-01', 'end_date' => '2026-11-30'], $this->initialize())
            ->assertOk()->json('result.structuredContent');
        $this->assertSame('unavailable_permission_denied', $r['rows'][0]['status']);
        $this->assertNull($r['rows'][0]['candidate_count']);
        $this->assertSame([], $r['rows'][0]['candidates']);
        $this->assertNull($r['summary']['exact_match']);
        $this->assertNull($r['totals_by_currency'][0]['exact_match_amount']);
        $this->assertNull($r['rows'][0]['account']);
    }

    public function test_invoice_details_exclude_party_identity_and_payment_history_without_transaction_permission(): void
    {
        $this->grant(['receivables-payables.view', 'customer_invoice.index']);
        $service = Mockery::mock(ReceivablesPayablesData::class);
        $service->shouldReceive('report')->once()->andReturn([
            'as_of' => '2026-11-20', 'timezone' => 'Europe/Bucharest', 'generated_at' => '2026-11-20 12:00:00',
            'receivables' => [['source_type' => 'issued_customer_invoice', 'source_id' => 88, 'source_reference' => 'INV-88', 'entity' => 'Private customer',
                'document_date' => '2026-11-01', 'due_date' => '2026-11-30', 'currency_code' => 'RON', 'invoice_total' => '100.00',
                'confirmed_allocated' => '20.00', 'outstanding_amount' => '80.00', 'credit_amount' => '0.00', 'balance_status' => 'supported',
                'payment_state' => 'partially_paid', 'aging_bucket' => 'current', 'days_overdue' => 0, 'allocation_count' => 1,
                'pending_allocation_count' => 0, 'reversed_allocation_count' => 0, 'reason_codes' => [], 'document_status' => 'issued',
                'source_status' => 'supported', 'allocation_history' => [['transaction_id' => 99]]]],
            'payables' => [], 'receivable_totals' => [], 'summary' => [],
        ]);
        $this->app->instance(ReceivablesPayablesData::class, $service);
        $r = $this->callTool('get_invoice_details', ['invoice_kind' => 'issued_customer_invoice', 'source_id' => 88], $this->initialize())
            ->assertOk()->json('result.structuredContent');
        $this->assertSame('available', $r['availability']);
        $this->assertSame('INV-88', $r['invoice']['source_reference']);
        $this->assertNull($r['invoice']['confirmed_allocated']);
        $this->assertSame('unavailable_permission_denied', $r['invoice']['payment_state']);
        $this->assertArrayNotHasKey('entity', $r['invoice']);
        $this->assertArrayNotHasKey('source_id', $r['invoice']);
        $this->assertArrayNotHasKey('allocation_history', $r['invoice']);
    }

    private function grant(array $permissions): void
    {
        $role = HexaRole::query()->create(['name' => 'Assistant', 'created_by_name' => 'Test', 'access' => ['Finance tools' => $permissions], 'guard' => hexa()->guard()]);
        $this->principal->roles()->attach($role->getKey());
    }

    private function initialize(): string
    {
        $r = $this->mcpPost(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-11-25', 'capabilities' => new \stdClass, 'clientInfo' => ['name' => 'mcp-finance-test', 'version' => '1.0'],
        ]])->assertOk();
        $id = $r->headers->get('Mcp-Session-Id');
        $this->assertNotEmpty($id);

        return (string) $id;
    }

    /** @param array<string, mixed> $arguments */
    private function callTool(string $name, array $arguments, string $session): TestResponse
    {
        return $this->mcpPost(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => (object) $arguments]], $session);
    }

    /** @param array<string, mixed> $payload */
    private function mcpPost(array $payload, ?string $session = null): TestResponse
    {
        Passport::actingAs($this->principal, $this->scopes);
        $server = ['HTTP_ACCEPT' => 'application/json, text/event-stream', 'CONTENT_TYPE' => 'application/json'];
        if ($session !== null) {
            $server['HTTP_MCP_SESSION_ID'] = $session;
        }

        return $this->call('POST', '/mcp', [], [], [], $server, json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
