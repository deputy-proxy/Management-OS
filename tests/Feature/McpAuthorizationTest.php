<?php

namespace Tests\Feature;

use App\Http\Middleware\CaptureMcpAuditContext;
use App\Mcp\Authorization\AuthorizedMcpCapabilityRegistrar;
use App\Mcp\Authorization\McpAuthorizationService;
use App\Mcp\Authorization\McpFieldFilter;
use App\Mcp\Authorization\McpToolPermission;
use App\Mcp\Authorization\McpToolPermissionRegistry;
use App\Mcp\Authorization\Scopes\McpGlobalRecordScope;
use App\Mcp\Authorization\Scopes\McpOwnedRecordScope;
use App\Mcp\Authorization\Scopes\McpTaskRecordScope;
use App\Models\User;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Mcp\Server\Builder;
use Mcp\Server\Capability\Registry\ReferenceHandler;
use Mcp\Server\Server;
use ReflectionFunction;
use ReflectionProperty;
use Tests\TestCase;

class McpAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);
        $keyPath = storage_path('framework/testing/mcp-authorization-keys');
        File::ensureDirectoryExists($keyPath, 0700, true);
        $privateKeyPath = $keyPath.'/oauth-private.key';
        $publicKeyPath = $keyPath.'/oauth-public.key';
        if (! is_file($privateKeyPath) || ! is_file($publicKeyPath)) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $this->assertNotFalse($key);
            $this->assertTrue(openssl_pkey_export($key, $privateKey));
            $details = openssl_pkey_get_details($key);
            $this->assertIsArray($details);
            file_put_contents($privateKeyPath, $privateKey);
            file_put_contents($publicKeyPath, $details['key']);
            chmod($privateKeyPath, 0600);
            chmod($publicKeyPath, 0600);
        }
        Passport::loadKeysFrom($keyPath);

        Schema::create('mcp_authorization_test_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id');
            $table->foreignId('assigned_to')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->string('label');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('mcp_authorization_test_records');

        parent::tearDown();
    }

    public function test_registry_denies_tools_without_a_permission_matrix_entry(): void
    {
        config(['mcp.tool_permissions' => []]);

        $this->expectException(\InvalidArgumentException::class);
        (new McpToolPermissionRegistry)->forTool('get_financial_overview');
    }

    public function test_registry_requires_each_tool_to_declare_read_or_write_operation_type(): void
    {
        config(['mcp.tool_permissions' => [
            'get_financial_overview' => [
                'mcp_scope' => 'mcp.finance.read',
                'domain_permissions' => ['finance.view_any'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => ['period' => true],
            ],
        ]]);

        $this->expectException(\InvalidArgumentException::class);
        (new McpToolPermissionRegistry)->forTool('get_financial_overview');
    }

    public function test_registry_rejects_reusing_a_tool_scope_for_multiple_tools(): void
    {
        config(['mcp.tool_permissions' => [
            'get_financial_overview' => [
                'mcp_scope' => 'mcp.finance.read',
                'operation_type' => 'read',
                'domain_permissions' => ['finance.view_any'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => ['period' => true],
            ],
            'search_financial_records' => [
                'mcp_scope' => 'mcp.finance.read',
                'operation_type' => 'read',
                'domain_permissions' => ['finance.view_any'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => ['id' => true],
            ],
        ]]);

        $this->expectException(\InvalidArgumentException::class);
        (new McpToolPermissionRegistry)->all();
    }

    public function test_permission_matrix_requires_domain_permission_and_explicit_field_allowlist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new McpToolPermission('get_financial_overview', 'mcp.finance.read', [], McpGlobalRecordScope::class, ['id' => true]);
    }

    public function test_tool_requires_configured_ai_identity_oauth_scope_and_domain_permission(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);

        app(McpAuthorizationService::class)->authorizeTool($principal, $this->permission());

        $this->assertTrue(true);
    }

    public function test_tool_is_denied_when_oauth_tool_scope_is_missing(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']]);
        Passport::actingAs($principal, ['mcp:use']);

        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizeTool($principal, $this->permission());
    }

    public function test_tool_is_denied_when_server_level_scope_is_missing(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']]);
        Passport::actingAs($principal, ['mcp.finance.read']);

        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizeTool($principal, $this->permission());
    }

    public function test_tool_is_denied_when_domain_permission_is_missing(): void
    {
        $principal = $this->principalWithPermissions([]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);

        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizeTool($principal, $this->permission());
    }

    public function test_tool_is_denied_when_ai_principal_has_no_roles_even_if_token_is_scoped(): void
    {
        $principal = User::factory()->create(['name' => 'AI Assistant', 'email' => 'chatgpt@edventure.ro']);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);

        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizeTool($principal, $this->permission());
    }

    public function test_tool_is_denied_to_owner_role_even_if_it_has_the_required_permission(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']], 'Owner');
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);

        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizeTool($principal, $this->permission());
    }

    public function test_tool_is_denied_when_an_additional_role_is_attached_to_the_ai_identity(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']]);
        $additionalRole = HexaRole::query()->create([
            'name' => 'Operations Reader',
            'created_by_name' => 'Test',
            'access' => ['tasks' => ['task.index']],
            'guard' => config('mcp.domain_permission_guard'),
        ]);
        $principal->roles()->attach($additionalRole->getKey());
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);

        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizeTool($principal, $this->permission());
    }

    public function test_tool_is_denied_to_an_account_other_than_the_configured_ai_identity(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']], email: 'staff@example.test');
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);

        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizeTool($principal, $this->permission());
    }

    public function test_permission_changes_are_rechecked_on_the_next_tool_call(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);
        $authorization = app(McpAuthorizationService::class);
        $permission = $this->permission();

        $authorization->authorizeTool($principal, $permission);
        HexaRole::query()->where('name', 'Assistant')->update(['access' => []]);

        $this->expectException(AuthorizationException::class);
        $authorization->authorizeTool($principal, $permission);
    }

    public function test_owned_scope_is_applied_before_aggregate_queries(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);
        $other = User::factory()->create();

        McpAuthorizationTestRecord::query()->create(['owner_id' => $principal->getKey(), 'label' => 'visible']);
        McpAuthorizationTestRecord::query()->create(['owner_id' => $other->getKey(), 'label' => 'hidden']);

        $permission = $this->permission(McpOwnedRecordScope::class);
        $query = app(McpAuthorizationService::class)->scopeQuery($principal, $permission, McpAuthorizationTestRecord::query());

        $this->assertSame(1, $query->count());
        $this->assertSame(['visible'], $query->pluck('label')->all());
    }

    public function test_task_scope_matches_existing_assignee_and_creator_visibility_before_counting(): void
    {
        $principal = $this->principalWithPermissions(['tasks' => ['task.index']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);
        $other = User::factory()->create();

        McpAuthorizationTestRecord::query()->create(['owner_id' => $other->getKey(), 'assigned_to' => $principal->getKey(), 'created_by' => $other->getKey(), 'label' => 'assigned']);
        McpAuthorizationTestRecord::query()->create(['owner_id' => $other->getKey(), 'assigned_to' => $other->getKey(), 'created_by' => $principal->getKey(), 'label' => 'created']);
        McpAuthorizationTestRecord::query()->create(['owner_id' => $other->getKey(), 'assigned_to' => $other->getKey(), 'created_by' => $other->getKey(), 'label' => 'restricted']);

        $permission = new McpToolPermission(
            'get_financial_overview',
            'mcp.finance.read',
            ['task.index'],
            McpTaskRecordScope::class,
            ['id' => true, 'label' => true],
        );
        $query = app(McpAuthorizationService::class)->scopeQuery($principal, $permission, McpAuthorizationTestRecord::query());

        $this->assertSame(2, $query->count());
        $this->assertSame(['assigned', 'created'], $query->orderBy('label')->pluck('label')->all());
    }

    public function test_task_view_all_permission_expands_scope_only_when_explicitly_granted(): void
    {
        $principal = $this->principalWithPermissions(['tasks' => ['task.index', 'task.view_all']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);
        $other = User::factory()->create();

        McpAuthorizationTestRecord::query()->create(['owner_id' => $other->getKey(), 'assigned_to' => $other->getKey(), 'created_by' => $other->getKey(), 'label' => 'restricted']);

        $permission = new McpToolPermission(
            'get_financial_overview',
            'mcp.finance.read',
            ['task.index'],
            McpTaskRecordScope::class,
            ['id' => true, 'label' => true],
        );
        $query = app(McpAuthorizationService::class)->scopeQuery($principal, $permission, McpAuthorizationTestRecord::query());

        $this->assertSame(1, $query->count());
    }

    public function test_direct_record_identifier_is_rechecked_against_the_declared_scope(): void
    {
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);
        $other = User::factory()->create();
        $record = new McpAuthorizationTestRecord(['owner_id' => $other->getKey(), 'label' => 'hidden']);

        $this->expectException(AuthorizationException::class);
        app(McpAuthorizationService::class)->authorizeRecord($principal, $this->permission(McpOwnedRecordScope::class), $record);
    }

    public function test_field_filter_uses_nested_allowlists_and_drops_sensitive_relationships(): void
    {
        $result = app(McpFieldFilter::class)->filter([
            'id' => 7,
            'name' => 'Example',
            'salary' => 9000,
            'password' => 'secret',
            'customer' => ['name' => 'Allowed', 'tax_id' => 'restricted'],
            'bank_account' => ['iban' => 'restricted'],
        ], [
            'id' => true,
            'name' => true,
            'customer' => ['name' => true],
        ]);

        $this->assertSame(['id' => 7, 'name' => 'Example', 'customer' => ['name' => 'Allowed']], $result);
    }

    public function test_field_filter_applies_the_allowlist_to_each_result_row(): void
    {
        $result = app(McpFieldFilter::class)->filter([
            ['id' => 1, 'name' => 'First', 'email' => 'private@example.test'],
            ['id' => 2, 'name' => 'Second', 'email' => 'private@example.test'],
        ], ['id' => true, 'name' => true]);

        $this->assertSame([['id' => 1, 'name' => 'First'], ['id' => 2, 'name' => 'Second']], $result);
    }

    public function test_registered_tool_wrapper_rechecks_authorization_and_filters_output_on_every_call(): void
    {
        config(['mcp.tool_permissions' => [
            'get_financial_overview' => [
                'mcp_scope' => 'mcp.finance.read',
                'operation_type' => 'read',
                'domain_permissions' => ['finance.view_any'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => ['id' => true, 'name' => true],
            ],
        ]]);
        $principal = $this->principalWithPermissions(['finance' => ['finance.view_any']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.finance.read']);

        $builder = Server::builder();
        app(McpAuthorizationTestRegistrar::class)->register($builder);
        $toolsProperty = new ReflectionProperty(Builder::class, 'tools');
        $tools = $toolsProperty->getValue($builder);
        $handler = $tools[0]['handler'];

        $this->assertSame(ReferenceHandler::class, (new ReflectionFunction($handler))->getClosureScopeClass()?->getName());
        request()->attributes->set('oauth_client_id', 'd8d14d2c-0a80-4f4c-aac8-bb3e67e2a11c');
        request()->attributes->set('request_id', 'mcp-wrapper-request-001');

        $this->assertSame(
            ['id' => 9, 'name' => 'Visible'],
            $handler(['record_id' => 9, '_session' => (object) [], '_request' => (object) []]),
        );
        $auditColumns = Schema::getColumnListing('mcp_execution_audits');
        $this->assertNotContains('arguments', $auditColumns);
        $this->assertNotContains('result', $auditColumns);

        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'tool_invocation',
            'tool_name' => 'get_financial_overview',
            'status' => 'succeeded',
            'ai_principal_id' => $principal->id,
            'oauth_client_id' => 'd8d14d2c-0a80-4f4c-aac8-bb3e67e2a11c',
            'request_id' => 'mcp-wrapper-request-001',
            'target_type' => 'record',
            'target_id' => '9',
        ]);

        HexaRole::query()->where('name', 'Assistant')->update(['access' => []]);
        try {
            $handler(['record_id' => 9, '_session' => (object) [], '_request' => (object) []]);
            $this->fail('The tool invocation should be denied after its permission is revoked.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('mcp_execution_audits', [
                'event_type' => 'tool_invocation',
                'tool_name' => 'get_financial_overview',
                'status' => 'failed',
                'error_code' => 'authorization_denied',
                'ai_principal_id' => $principal->id,
            ]);
        }
    }

    public function test_registered_tool_wrapper_rejects_unauthenticated_invocations(): void
    {
        config(['mcp.tool_permissions' => [
            'get_financial_overview' => [
                'mcp_scope' => 'mcp.finance.read',
                'operation_type' => 'read',
                'domain_permissions' => ['finance.view_any'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => ['id' => true],
            ],
        ]]);

        $builder = Server::builder();
        app(McpAuthorizationTestRegistrar::class)->register($builder);
        $toolsProperty = new ReflectionProperty(Builder::class, 'tools');
        $handler = $toolsProperty->getValue($builder)[0]['handler'];
        Auth::guard('api')->forgetUser();

        try {
            $handler(['record_id' => 9, '_session' => (object) [], '_request' => (object) []]);
            $this->fail('An unauthenticated MCP tool invocation must be denied.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('mcp_execution_audits', [
                'event_type' => 'tool_invocation',
                'tool_name' => 'get_financial_overview',
                'status' => 'failed',
                'error_code' => 'authorization_denied',
                'ai_principal_id' => null,
            ]);
        }
    }

    public function test_mcp_audit_context_rejects_tokens_without_a_valid_client_id_claim(): void
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['aud' => 'https://example.test/mcp'], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer header.'.$payload.'.signature',
        ]);

        app(CaptureMcpAuditContext::class)->handle($request, static fn () => response()->noContent());

        $this->assertNull($request->attributes->get('oauth_client_id'));
    }

    public function test_mcp_audit_context_captures_client_id_from_the_authenticated_jwt_claim(): void
    {
        $clientId = 'd8d14d2c-0a80-4f4c-aac8-bb3e67e2a11c';
        $payload = rtrim(strtr(base64_encode(json_encode(['client_id' => $clientId], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer header.'.$payload.'.signature',
        ]);

        app(CaptureMcpAuditContext::class)->handle($request, static fn () => response()->noContent());

        $this->assertSame($clientId, $request->attributes->get('oauth_client_id'));
    }

    public function test_permission_matrix_requires_human_approval_for_write_tools(): void
    {
        config(['mcp.tool_permissions' => [
            'create_task' => [
                'mcp_scope' => 'mcp.operations.tasks.write',
                'domain_permissions' => ['task.create'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => ['approval_id' => true],
                'operation_type' => 'write',
            ],
        ]]);

        $this->expectException(\InvalidArgumentException::class);
        (new McpToolPermissionRegistry)->forTool('create_task');
    }

    public function test_approval_request_tool_creates_pending_request_without_running_a_domain_mutation(): void
    {
        config(['mcp.tool_permissions' => [
            'create_task' => [
                'mcp_scope' => 'mcp.operations.tasks.write',
                'domain_permissions' => ['task.create'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => [
                    'approval_id' => true,
                    'status' => true,
                    'expires_at' => true,
                    'preview_summary' => true,
                ],
                'operation_type' => 'write',
                'requires_approval' => true,
            ],
        ]]);

        $principal = $this->principalWithPermissions(['tasks' => ['task.create']]);
        Passport::actingAs($principal, ['mcp:use', 'mcp.operations.tasks.write']);
        request()->attributes->set('oauth_client_id', 'd8d14d2c-0a80-4f4c-aac8-bb3e67e2a11c');

        $builder = Server::builder();
        app(McpAuthorizationTestRegistrar::class)->registerApprovalRequestTool($builder);
        $toolsProperty = new ReflectionProperty(Builder::class, 'tools');
        $handler = $toolsProperty->getValue($builder)[0]['handler'];

        $result = $handler([
            'title' => 'Approval-gated task',
            'idempotency_key' => 'approval-request-001',
            '_session' => (object) [],
            '_request' => (object) [],
        ]);

        $this->assertSame('pending', $result['status']);
        $this->assertArrayHasKey('approval_id', $result);
        $this->assertDatabaseHas('mcp_approvals', [
            'id' => $result['approval_id'],
            'tool_name' => 'create_task',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('mcp_execution_audits', [
            'event_type' => 'approval_requested',
            'tool_name' => 'create_task',
            'status' => 'pending',
        ]);
        $this->assertSame(0, DB::table('mcp_execution_audits')->where('event_type', 'approved_operation_execution')->count());
    }

    public function test_generic_authorized_tool_registration_rejects_write_operations(): void
    {
        config(['mcp.tool_permissions' => [
            'create_task' => [
                'mcp_scope' => 'mcp.operations.tasks.write',
                'domain_permissions' => ['task.create'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => ['id' => true],
                'operation_type' => 'write',
                'requires_approval' => true,
            ],
        ]]);

        $this->expectException(\LogicException::class);
        app(McpAuthorizationTestRegistrar::class)->registerWriteTool(Server::builder());
    }

    public function test_registry_exposes_only_registered_tool_scopes_to_passport(): void
    {
        config(['mcp.tool_permissions' => [
            'get_financial_overview' => [
                'mcp_scope' => 'mcp.finance.read',
                'operation_type' => 'read',
                'domain_permissions' => ['finance.view_any'],
                'record_scope' => McpGlobalRecordScope::class,
                'allowed_fields' => ['period' => true],
            ],
        ]]);

        $this->assertSame(
            ['mcp.finance.read' => 'Invoke the get_financial_overview MCP tool.'],
            (new McpToolPermissionRegistry)->passportScopes(),
        );
    }

    /** @param array<string, mixed> $permissions */
    private function principalWithPermissions(array $permissions, string $roleName = 'Assistant', string $email = 'chatgpt@edventure.ro'): User
    {
        $principal = User::factory()->create(['name' => 'AI Assistant', 'email' => $email]);
        $role = HexaRole::query()->create([
            'name' => $roleName,
            'created_by_name' => 'Test',
            'access' => $permissions,
            'guard' => hexa()->guard(),
        ]);
        $principal->roles()->attach($role->getKey());

        return $principal;
    }

    private function permission(string $recordScope = McpGlobalRecordScope::class): McpToolPermission
    {
        return new McpToolPermission(
            'get_financial_overview',
            'mcp.finance.read',
            ['finance.view_any'],
            $recordScope,
            ['id' => true, 'name' => true],
        );
    }
}

class McpAuthorizationTestRecord extends Model
{
    protected $table = 'mcp_authorization_test_records';

    public $timestamps = false;

    protected $guarded = [];
}

class McpAuthorizationTestRegistrar extends AuthorizedMcpCapabilityRegistrar
{
    public function register(Builder $builder): void
    {
        $this->addAuthorizedTool(
            $builder,
            'get_financial_overview',
            static fn (User $principal, array $arguments): array => [
                'id' => $arguments['record_id'] ?? 0,
                'name' => 'Visible',
                'salary' => 999999,
                'password' => 'never-return',
            ],
            'Returns a filtered financial overview.',
            [
                'type' => 'object',
                'properties' => ['record_id' => ['type' => 'integer']],
                'required' => ['record_id'],
            ],
        );
    }

    public function registerWriteTool(Builder $builder): void
    {
        $this->addAuthorizedTool(
            $builder,
            'create_task',
            static fn (User $principal, array $arguments): array => ['id' => 1],
            'Creates a task.',
            ['type' => 'object'],
        );
    }

    public function registerApprovalRequestTool(Builder $builder): void
    {
        $this->addApprovalRequestTool(
            $builder,
            'create_task',
            static fn (User $principal, array $arguments): array => [
                'preview' => ['action' => 'Create task', 'title' => $arguments['title']],
                'target_type' => 'task',
                'target_id' => null,
            ],
            'Requests an approval to create a task.',
            [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'idempotency_key' => ['type' => 'string'],
                ],
                'required' => ['title', 'idempotency_key'],
            ],
        );
    }
}
