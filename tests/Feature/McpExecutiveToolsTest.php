<?php

namespace Tests\Feature;

use App\Mcp\Authorization\McpToolPermissionRegistry;
use App\Mcp\Server\McpServerFactory;
use App\Mcp\Tools\Executive\ExecutiveMcpCapabilityRegistrar;
use App\Models\Customer;
use App\Models\KpiAlert;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Services\KpiAggregationService;
use App\Services\KpiDefinitionService;
use Carbon\CarbonImmutable;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class McpExecutiveToolsTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionPath;

    private string $passportKeyPath;

    private User $principal;

    /** @var list<string> */
    private array $scopes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passportKeyPath = storage_path('framework/testing/passport-keys');
        File::ensureDirectoryExists($this->passportKeyPath, 0700, true);
        $this->ensurePassportKeys();
        Passport::loadKeysFrom($this->passportKeyPath);

        $this->sessionPath = storage_path('framework/testing/mcp-executive-sessions');
        config()->set('mcp.session_path', $this->sessionPath);
        File::deleteDirectory($this->sessionPath);

        $this->principal = User::factory()->create([
            'name' => 'MCP executive principal',
            'email' => 'executive-tools-test@edventure.ro',
        ]);
        config()->set('mcp.ai_principal_email', $this->principal->email);

        $this->scopes = [
            'mcp:use',
            ...array_keys((new McpToolPermissionRegistry)->passportScopes()),
        ];

        app()->instance(
            McpServerFactory::class,
            new McpServerFactory([app(ExecutiveMcpCapabilityRegistrar::class)]),
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessionPath);

        parent::tearDown();
    }

    public function test_all_six_read_only_tools_are_discovered_with_closed_input_schemas(): void
    {
        $this->grantPermissions(['overview-dashboard.view', 'kpi_alert.view_own']);

        $sessionId = $this->initializeSession();
        $response = $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => new \stdClass,
        ], $sessionId);

        $response->assertOk();
        $tools = $response->json('result.tools');
        $this->assertIsArray($tools);
        $this->assertEqualsCanonicalizing([
            'get_executive_overview',
            'get_management_kpis',
            'get_kpi_alerts',
            'get_business_health',
            'get_management_priorities',
            'get_period_comparison',
        ], array_column($tools, 'name'));

        foreach ($tools as $tool) {
            $this->assertFalse($tool['inputSchema']['additionalProperties'] ?? true);
        }

        $comparison = collect($tools)->firstWhere('name', 'get_period_comparison');
        $this->assertSame(['current_period', 'comparison_period'], $comparison['inputSchema']['required']);
        $alerts = collect($tools)->firstWhere('name', 'get_kpi_alerts');
        $this->assertSame(50, $alerts['inputSchema']['properties']['limit']['maximum']);
    }

    public function test_executive_overview_only_includes_domains_with_optional_permissions(): void
    {
        $this->grantPermissions(['overview-dashboard.view', 'customer.index']);
        Customer::factory()->count(2)->create();

        $sessionId = $this->initializeSession();
        $response = $this->callTool('get_executive_overview', [], $sessionId);

        $response->assertOk();
        $metrics = $response->json('result.structuredContent.metrics');
        $this->assertSame(['Customers'], array_column($metrics, 'label'));
        $this->assertSame([2], array_column($metrics, 'value'));
        $this->assertStringNotContainsString('Student records', (string) $response->getContent());
    }

    public function test_management_kpis_return_unavailable_instead_of_restricted_source_counts(): void
    {
        $this->grantPermissions(['overview-dashboard.view']);
        Customer::factory()->count(3)->create();

        $sessionId = $this->initializeSession();
        $response = $this->callTool('get_management_kpis', [], $sessionId);

        $response->assertOk();
        $results = $response->json('result.structuredContent.results');
        $customerCount = collect($results)->firstWhere('key', 'customer_record_count');

        $this->assertSame('unavailable_permission_denied', $customerCount['availability']);
        $this->assertNull($customerCount['value']);
        $this->assertStringNotContainsString('"value":3', (string) $response->getContent());
    }

    public function test_management_kpis_distinguish_a_real_zero_from_an_unavailable_source(): void
    {
        $this->grantPermissions(['overview-dashboard.view', 'customer.index']);

        $sessionId = $this->initializeSession();
        $response = $this->callTool('get_management_kpis', [], $sessionId);

        $response->assertOk();
        $results = $response->json('result.structuredContent.results');
        $customerCount = collect($results)->firstWhere('key', 'customer_record_count');

        $this->assertSame(0, $customerCount['value']);
        $this->assertSame('available', $customerCount['availability']);
    }

    public function test_business_health_does_not_invent_a_composite_score_and_period_comparison_is_explicitly_unavailable(): void
    {
        $this->grantPermissions(['overview-dashboard.view']);

        $sessionId = $this->initializeSession();
        $health = $this->callTool('get_business_health', [], $sessionId);
        $health->assertOk()
            ->assertJsonPath('result.structuredContent.assessment_status', 'not_scored')
            ->assertJsonPath('result.structuredContent.catalogue_approval_status', 'provisional');

        $comparison = $this->callTool('get_period_comparison', [
            'current_period' => ['start_date' => '2026-11-01', 'end_date' => '2026-11-20'],
            'comparison_period' => ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'],
        ], $sessionId);

        $comparison->assertOk()
            ->assertJsonPath('result.structuredContent.availability', 'unavailable_historical_period_not_supported')
            ->assertJsonPath('result.structuredContent.results', []);
    }

    public function test_executive_overview_returns_a_safe_kpi_error_state_without_exception_details(): void
    {
        $this->grantPermissions(['overview-dashboard.view']);
        $service = \Mockery::mock(KpiAggregationService::class);
        $service->shouldReceive('current')->once()->andThrow(new \RuntimeException('private KPI backend details'));
        app()->instance(KpiAggregationService::class, $service);

        $sessionId = $this->initializeSession();
        $response = $this->callTool('get_executive_overview', [], $sessionId);

        $response->assertOk()
            ->assertJsonPath('result.structuredContent.kpis.status', 'error');
        $this->assertStringNotContainsString('private KPI backend details', (string) $response->getContent());
    }

    public function test_management_priorities_preserve_task_participant_visibility_and_bound_results(): void
    {
        $this->grantPermissions(['overview-dashboard.view', 'task.index']);
        $this->actingAs($this->principal);
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);
        $other = User::factory()->create();

        Task::factory()->create([
            'action' => 'Visible overdue task',
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
            'assigned_to' => $this->principal->id,
            'created_by' => $this->principal->id,
            'status' => 'pending',
            'due_date' => CarbonImmutable::yesterday()->toDateString(),
        ]);
        $hiddenTask = Task::factory()->create([
            'action' => 'Hidden overdue task',
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
            'assigned_to' => $other->id,
            'created_by' => $other->id,
            'status' => 'pending',
            'due_date' => CarbonImmutable::yesterday()->toDateString(),
        ]);
        Task::query()->whereKey($hiddenTask->getKey())->update(['created_by' => $other->id]);

        $sessionId = $this->initializeSession();
        $response = $this->callTool('get_management_priorities', ['limit' => 1], $sessionId);

        $response->assertOk()
            ->assertJsonPath('result.structuredContent.overdue_count', 1)
            ->assertJsonPath('result.structuredContent.overdue_tasks.0.action', 'Visible overdue task')
            ->assertJsonPath('result.structuredContent.overdue_tasks.0.project', null)
            ->assertJsonPath('result.structuredContent.overdue_tasks.0.phase', null);
        $this->assertStringNotContainsString('Hidden overdue task', (string) $response->getContent());

        $overview = $this->callTool('get_executive_overview', [], $sessionId);
        $overview->assertOk()
            ->assertJsonPath('result.structuredContent.overdue_tasks.0.action', 'Visible overdue task')
            ->assertJsonPath('result.structuredContent.overdue_tasks.0.project', null);
    }

    public function test_kpi_alerts_are_owner_scoped_and_bounded(): void
    {
        $this->grantPermissions(['overview-dashboard.view', 'kpi_alert.view_own', 'kpi_alert.index']);
        $this->actingAs($this->principal);
        Gate::define('kpi_definition.create', fn (): bool => true);
        Gate::define('kpi_definition.approve', fn (): bool => true);

        $definition = app(KpiDefinitionService::class)->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => CarbonImmutable::tomorrow('Europe/Bucharest')->toDateString(),
            'threshold_direction' => 'higher_is_worse',
            'warning_threshold' => 10,
            'critical_threshold' => 20,
            'alerts_enabled' => true,
            'alert_owner_id' => $this->principal->id,
            'change_summary' => 'Create an owner-scoped MCP alert fixture.',
        ]);
        $definition = app(KpiDefinitionService::class)->activate($definition);
        $other = User::factory()->create();

        foreach ([$this->principal, $other] as $owner) {
            KpiAlert::query()->create([
                'kpi_definition_id' => $definition->id,
                'owner_id' => $owner->id,
                'dedupe_key' => 'mcp-alert-owner-'.$owner->id,
                'severity' => 'warning',
                'status' => KpiAlert::STATUS_OPEN,
                'observed_value' => 12,
                'trigger_threshold' => 10,
                'evidence' => ['kpi_key' => 'customer_record_count', 'source_record_ids' => [99]],
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        $sessionId = $this->initializeSession();
        $response = $this->callTool('get_kpi_alerts', ['status' => 'open', 'limit' => 1], $sessionId);

        $response->assertOk()
            ->assertJsonPath('result.structuredContent.returned_count', 1)
            ->assertJsonPath('result.structuredContent.total_visible_count', 1)
            ->assertJsonPath('result.structuredContent.has_more', false);
        $this->assertStringNotContainsString('source_record_ids', (string) $response->getContent());
    }

    private function grantPermissions(array $permissions): void
    {
        $role = HexaRole::query()->create([
            'name' => 'Assistant',
            'created_by_name' => 'Test',
            'access' => ['Executive tools' => $permissions],
            'guard' => hexa()->guard(),
        ]);
        $this->principal->roles()->attach($role->getKey());
    }

    private function ensurePassportKeys(): void
    {
        $privateKeyPath = $this->passportKeyPath.'/oauth-private.key';
        $publicKeyPath = $this->passportKeyPath.'/oauth-public.key';
        if (is_file($privateKeyPath) && is_file($publicKeyPath)) {
            return;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey));
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        $this->assertArrayHasKey('key', $details);
        file_put_contents($privateKeyPath, $privateKey);
        file_put_contents($publicKeyPath, $details['key']);
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0600);
    }

    private function initializeSession(): string
    {
        $response = $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'mcp-executive-tools-test', 'version' => '1.0.0'],
            ],
        ]);

        $response->assertOk();
        $sessionId = $response->headers->get('Mcp-Session-Id');
        $this->assertNotEmpty($sessionId);

        return (string) $sessionId;
    }

    /** @param array<string, mixed> $arguments */
    private function callTool(string $name, array $arguments, string $sessionId): TestResponse
    {
        return $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => $name,
                'arguments' => (object) $arguments,
            ],
        ], $sessionId);
    }

    /** @param array<string, mixed> $payload */
    private function mcpPost(array $payload, ?string $sessionId = null): TestResponse
    {
        Passport::actingAs($this->principal, $this->scopes);
        $server = [
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'CONTENT_TYPE' => 'application/json',
        ];
        if ($sessionId !== null) {
            $server['HTTP_MCP_SESSION_ID'] = $sessionId;
        }

        return $this->call('POST', '/mcp', [], [], [], $server, json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
