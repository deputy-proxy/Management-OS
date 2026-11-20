<?php

namespace Tests\Feature;

use App\Mcp\Authorization\McpAuthorizationService;
use App\Mcp\Authorization\McpToolPermissionRegistry;
use App\Mcp\Server\McpServerFactory;
use App\Mcp\Tools\Crm\CrmMcpCapabilityRegistrar;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerInteraction;
use App\Models\CustomerLead;
use App\Models\CustomerUnitEconomicsRun;
use App\Models\User;
use App\Services\CustomerLeadAttributionHistory;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class McpCrmToolsTest extends TestCase
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

        $this->keyPath = storage_path('framework/testing/mcp-crm-passport');
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

        $this->sessionPath = storage_path('framework/testing/mcp-crm-sessions');
        config()->set('mcp.session_path', $this->sessionPath);
        File::deleteDirectory($this->sessionPath);

        $this->principal = User::factory()->create(['email' => 'mcp-crm@example.test']);
        config()->set('mcp.ai_principal_email', $this->principal->email);
        $this->scopes = ['mcp:use', ...array_keys((new McpToolPermissionRegistry)->passportScopes())];

        app()->instance(
            McpServerFactory::class,
            new McpServerFactory([app(CrmMcpCapabilityRegistrar::class)]),
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessionPath);
        parent::tearDown();
    }

    public function test_seven_crm_tools_are_read_only_and_use_closed_schemas(): void
    {
        $this->grant(['customer.index', 'customer_contract.index', 'customer_interaction.index', 'customer_lead.index', 'finance-unit-economics.view']);

        $tools = $this->mcpPost([
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new \stdClass,
        ], $this->initialize())->assertOk()->json('result.tools');

        $expected = [
            'search_customers',
            'get_customer_details',
            'get_customer_contracts',
            'get_customer_interactions',
            'get_sales_pipeline',
            'get_lead_details',
            'get_customer_economics',
        ];
        $this->assertEqualsCanonicalizing($expected, array_column($tools, 'name'));
        foreach ($tools as $tool) {
            $this->assertFalse($tool['inputSchema']['additionalProperties'] ?? true);
            $this->assertSame('read', (new McpToolPermissionRegistry)->forTool($tool['name'])->operationType);
            $this->assertFalse((new McpToolPermissionRegistry)->forTool($tool['name'])->requiresApproval);
        }

        $search = collect($tools)->firstWhere('name', 'search_customers');
        $this->assertSame(100, $search['inputSchema']['properties']['limit']['maximum']);
        $this->assertSame(['query'], $search['inputSchema']['required']);
    }

    public function test_customer_search_filters_paginates_and_omits_personal_identifiers(): void
    {
        $this->grant(['customer.index']);
        Customer::factory()->create([
            'name' => 'EdVenture Alpha',
            'customer_type' => 'company',
            'email' => 'alpha-private@example.test',
            'cnp_cui' => 'RO-PRIVATE-1',
            'address' => 'Private address one',
        ]);
        Customer::factory()->create([
            'name' => 'EdVenture Beta',
            'customer_type' => 'company',
            'email' => 'beta-private@example.test',
            'cnp_cui' => 'RO-PRIVATE-2',
            'address' => 'Private address two',
        ]);
        Customer::factory()->create(['name' => 'Unrelated customer', 'customer_type' => 'person']);

        $session = $this->initialize();
        $first = $this->callTool('search_customers', [
            'query' => 'EdVenture', 'customer_type' => 'company', 'limit' => 1, 'sort_by' => 'name',
        ], $session)->assertOk()->json('result.structuredContent');

        $this->assertSame(2, $first['total_count']);
        $this->assertSame(1, $first['returned_count']);
        $this->assertTrue($first['has_more']);
        $this->assertSame('EdVenture Alpha', $first['customers'][0]['name']);
        $this->assertArrayNotHasKey('email', $first['customers'][0]);
        $this->assertArrayNotHasKey('cnp_cui', $first['customers'][0]);
        $this->assertArrayNotHasKey('address', $first['customers'][0]);

        $second = $this->callTool('search_customers', [
            'query' => 'EdVenture', 'customer_type' => 'company', 'limit' => 1, 'offset' => 1,
        ], $session)->assertOk()->json('result.structuredContent');
        $this->assertSame('EdVenture Beta', $second['customers'][0]['name']);

        $content = (string) $this->callTool('search_customers', ['query' => 'EdVenture'], $session)->getContent();
        $this->assertStringNotContainsString('alpha-private@example.test', $content);
        $this->assertStringNotContainsString('RO-PRIVATE-1', $content);
        $this->assertStringNotContainsString('Private address one', $content);
    }

    public function test_customer_detail_contracts_and_interactions_use_separate_permissions_and_safe_fields(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Contract-safe customer',
            'email' => 'secret-customer@example.test',
            'cnp_cui' => 'SECRET-CUI',
            'address' => 'Secret address',
        ]);
        CustomerContract::factory()->create([
            'customer_id' => $customer->getKey(),
            'contract_code' => 'CTR-2026-01',
            'document' => 'private/contracts/signed.pdf',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'signed_at' => '2026-01-01 10:00:00',
        ]);
        CustomerInteraction::factory()->create([
            'customer_id' => $customer->getKey(),
            'description' => 'Highly confidential conversation notes',
            'initiator' => 'Private staff name',
            'reason' => 'Renewal',
            'timestamp' => '2026-11-10 09:30:00',
            'interaction_rating' => 5,
        ]);

        $this->grant(['customer.index', 'customer_contract.index', 'customer_interaction.index']);
        $session = $this->initialize();

        $detail = $this->callTool('get_customer_details', ['customer_id' => $customer->getKey()], $session)
            ->assertOk()->json('result.structuredContent');
        $this->assertSame('Contract-safe customer', $detail['customer']['name']);
        $this->assertArrayNotHasKey('email', $detail['customer']);
        $this->assertArrayNotHasKey('cnp_cui', $detail['customer']);
        $this->assertArrayNotHasKey('address', $detail['customer']);

        $contracts = $this->callTool('get_customer_contracts', ['customer_id' => $customer->getKey()], $session)
            ->assertOk()->json('result.structuredContent');
        $this->assertSame('CTR-2026-01', $contracts['contracts'][0]['contract_code']);
        $this->assertArrayNotHasKey('document', $contracts['contracts'][0]);
        $this->assertArrayNotHasKey('customer_id', $contracts['contracts'][0]);

        $interactions = $this->callTool('get_customer_interactions', ['customer_id' => $customer->getKey()], $session)
            ->assertOk()->json('result.structuredContent');
        $this->assertSame(5, $interactions['interactions'][0]['interaction_rating']);
        $this->assertArrayNotHasKey('description', $interactions['interactions'][0]);
        $this->assertArrayNotHasKey('initiator', $interactions['interactions'][0]);

        $content = (string) $this->callTool('get_customer_interactions', ['customer_id' => $customer->getKey()], $session)->getContent();
        $this->assertStringNotContainsString('Highly confidential conversation notes', $content);
        $this->assertStringNotContainsString('Private staff name', $content);
    }

    public function test_sales_pipeline_and_lead_details_preserve_attribution_without_contact_details(): void
    {
        $this->grant(['customer_lead.index']);
        $lead = CustomerLead::factory()->create([
            'full_name' => 'Lead Private Person',
            'email' => 'lead-private@example.test',
            'phone' => '+40123456789',
            'source' => 'webinar',
            'source_category' => 'event',
            'campaign_name' => 'Autumn webinar',
            'referral_partner' => 'Partner A',
            'status' => 'new',
        ]);
        $lead->forceFill(['status' => 'contacted'])->save();

        $convertedCustomer = Customer::factory()->create(['name' => 'Converted customer']);
        $convertedLead = CustomerLead::factory()->create([
            'full_name' => 'Converted lead person',
            'email' => 'converted-private@example.test',
            'status' => 'converted',
            'source_category' => 'paid_search',
            'campaign_name' => 'Search campaign',
            'converted_customer_id' => $convertedCustomer->getKey(),
            'converted_at' => now(),
        ]);
        app(CustomerLeadAttributionHistory::class)->recordConversion($convertedLead);

        $session = $this->initialize();
        $details = $this->callTool('get_lead_details', ['lead_id' => $lead->getKey()], $session)
            ->assertOk()->json('result.structuredContent');

        $this->assertSame('Lead Private Person', $details['lead']['full_name']);
        $this->assertSame('contacted', $details['lead']['status']);
        $this->assertSame('event', $details['lead']['source_category']);
        $this->assertNotEmpty($details['status_history']);
        $this->assertNotEmpty($details['attribution_history']);
        $this->assertArrayNotHasKey('source', $details['attribution_history'][0]);
        $this->assertArrayNotHasKey('email', $details['lead']);
        $this->assertArrayNotHasKey('phone', $details['lead']);

        $pipeline = $this->callTool('get_sales_pipeline', [], $session)
            ->assertOk()->json('result.structuredContent');
        $this->assertSame(1, $pipeline['open_lead_count']);
        $this->assertSame(1, collect($pipeline['lead_statuses'])->firstWhere('status', 'contacted')['count']);
        $this->assertSame(2, $pipeline['attribution']['campaign_tagged_count']);
        $this->assertSame(1, collect($pipeline['attribution']['source_categories'])->firstWhere('source_category', 'paid_search')['count']);

        $convertedDetails = $this->callTool('get_lead_details', ['lead_id' => $convertedLead->getKey()], $session)
            ->assertOk()->json('result.structuredContent');
        $conversion = collect($convertedDetails['attribution_history'])->firstWhere('event_type', 'conversion');
        $this->assertSame('paid_search', $conversion['first_touch_source_category']);
        $this->assertArrayNotHasKey('converted_customer_id', $convertedDetails['lead']);

        $content = (string) $this->callTool('get_lead_details', ['lead_id' => $lead->getKey()], $session)->getContent();
        $this->assertStringNotContainsString('lead-private@example.test', $content);
        $this->assertStringNotContainsString('+40123456789', $content);
        $this->assertStringNotContainsString('topic_interest', $content);
    }

    public function test_customer_economics_reads_immutable_aggregates_without_source_snapshots(): void
    {
        $this->grant(['finance-unit-economics.view']);
        $run = CustomerUnitEconomicsRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'policy_version' => 'test-policy-v1',
            'policy_snapshot' => ['internal_policy' => 'do not expose'],
            'exceptions' => [['type' => 'revenue_data_incomplete'], ['type' => 'revenue_data_incomplete']],
        ]);
        $run->results()->create([
            'source_category' => 'organic_search',
            'currency_code' => 'RON',
            'acquired_customer_count' => 4,
            'cost_entry_count' => 2,
            'acquisition_cost_amount' => '400.00',
            'cac_amount' => '100.00',
            'revenue_amount' => null,
            'ltv_amount' => null,
            'revenue_coverage_count' => 0,
            'revenue_unknown_count' => 4,
            'retention_coverage_count' => 2,
            'retention_unknown_count' => 2,
            'result_status' => 'incomplete',
            'source_snapshot' => ['customer_ids' => [11, 12], 'lead_ids' => [21], 'cost_entry_ids' => [31]],
        ]);

        $beforeRuns = CustomerUnitEconomicsRun::query()->count();
        $session = $this->initialize();
        $result = $this->callTool('get_customer_economics', ['run_id' => $run->run_id], $session)
            ->assertOk()->json('result.structuredContent');

        $this->assertSame('available', $result['availability']);
        $this->assertSame($run->run_id, $result['run_id']);
        $this->assertSame('2026-10-01', $result['period_start']);
        $this->assertSame(2, $result['exception_count']);
        $this->assertSame('revenue_data_incomplete', $result['exception_code_counts'][0]['code']);
        $this->assertSame(4, $result['results'][0]['acquired_customer_count']);
        $this->assertSame(4, $result['results'][0]['revenue_unknown_count']);
        $this->assertNull($result['results'][0]['ltv_amount']);
        $this->assertSame($beforeRuns, CustomerUnitEconomicsRun::query()->count());

        $content = (string) $this->callTool('get_customer_economics', [], $session)->getContent();
        $this->assertStringNotContainsString('customer_ids', $content);
        $this->assertStringNotContainsString('cost_entry_ids', $content);
        $this->assertStringNotContainsString('internal_policy', $content);
    }

    public function test_crm_tools_are_denied_without_the_specific_domain_permission(): void
    {
        $this->grant(['customer.index']);
        $permission = (new McpToolPermissionRegistry)->forTool('get_sales_pipeline');

        $this->assertFalse(app(McpAuthorizationService::class)->hasDomainPermission($this->principal, 'customer_lead.index'));
        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizePrincipalForTool($this->principal, $permission);
    }

    private function grant(array $permissions): void
    {
        $role = HexaRole::query()->create([
            'name' => 'Assistant',
            'created_by_name' => 'Test',
            'access' => ['CRM tools' => $permissions],
            'guard' => hexa()->guard(),
        ]);
        $this->principal->roles()->attach($role->getKey());
    }

    private function initialize(): string
    {
        $response = $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'mcp-crm-test', 'version' => '1.0'],
            ],
        ])->assertOk();
        $session = $response->headers->get('Mcp-Session-Id');
        $this->assertNotEmpty($session);

        return (string) $session;
    }

    /** @param array<string, mixed> $arguments */
    private function callTool(string $name, array $arguments, string $session): TestResponse
    {
        return $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => (object) $arguments],
        ], $session);
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
