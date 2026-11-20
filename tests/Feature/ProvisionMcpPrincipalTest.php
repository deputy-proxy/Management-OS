<?php

namespace Tests\Feature;

use App\Mcp\Authorization\Scopes\McpGlobalRecordScope;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProvisionMcpPrincipalTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_a_deny_by_default_ai_principal_with_the_permissionless_assistant_role(): void
    {
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);
        $assistantRole = $this->createAssistantRole();

        $this->artisan('mcp:provision-principal')
            ->expectsOutputToContain('with only MCP-mapped Assistant permissions')
            ->assertSuccessful();

        $principal = User::query()->where('email', 'chatgpt@edventure.ro')->firstOrFail();

        $this->assertSame('AI Assistant', $principal->name);
        $this->assertNull($principal->email_verified_at);
        $this->assertEqualsCanonicalizing([$assistantRole->getKey()], $principal->roles()->pluck('hexa_roles.id')->all());
        $this->assertFalse($principal->canAccessPanel(Filament::getCurrentPanel() ?? new Panel));
        $this->assertFalse(hexa()->user($principal)->can('users.view_any'));
    }

    public function test_command_accepts_an_existing_principal_with_only_the_permissionless_assistant_role(): void
    {
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);
        $assistantRole = $this->createAssistantRole();
        $principal = User::factory()->create(['name' => 'AI Assistant', 'email' => 'chatgpt@edventure.ro']);
        $principal->roles()->attach($assistantRole->getKey());

        $this->artisan('mcp:provision-principal')
            ->expectsOutputToContain('with only MCP-mapped Assistant permissions')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing([$assistantRole->getKey()], $principal->fresh()->roles()->pluck('hexa_roles.id')->all());
        $this->assertFalse($principal->fresh()->canAccessPanel(Filament::getCurrentPanel() ?? new Panel));
        $this->assertFalse(hexa()->user($principal->fresh())->can('users.view_any'));
    }

    public function test_command_accepts_only_permissions_declared_by_the_mcp_matrix(): void
    {
        config([
            'mcp.ai_principal_email' => 'chatgpt@edventure.ro',
            'mcp.tool_permissions' => [
                'get_financial_overview' => [
                    'mcp_scope' => 'mcp.finance.read',
                    'operation_type' => 'read',
                    'domain_permissions' => ['finance.view_any'],
                    'record_scope' => McpGlobalRecordScope::class,
                    'allowed_fields' => ['period' => true],
                ],
            ],
        ]);
        $assistantRole = $this->createAssistantRole(['finance' => ['finance.view_any']]);

        $this->artisan('mcp:provision-principal')
            ->expectsOutputToContain('with only MCP-mapped Assistant permissions')
            ->assertSuccessful();

        $principal = User::query()->where('email', 'chatgpt@edventure.ro')->firstOrFail();
        $this->assertEqualsCanonicalizing([$assistantRole->getKey()], $principal->roles()->pluck('hexa_roles.id')->all());
    }

    public function test_command_refuses_to_provision_when_assistant_role_has_permissions(): void
    {
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);
        $this->createAssistantRole(['users' => ['view_any']]);

        $this->artisan('mcp:provision-principal')
            ->expectsOutputToContain('not granted by the MCP permission matrix')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'chatgpt@edventure.ro']);
    }

    public function test_command_refuses_to_reuse_an_existing_account_with_an_unexpected_role(): void
    {
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);
        $this->createAssistantRole();
        $principal = User::factory()->create(['name' => 'AI Assistant', 'email' => 'chatgpt@edventure.ro']);
        $role = HexaRole::query()->create([
            'name' => 'MCP unexpected role',
            'created_by_name' => 'Test',
            'access' => ['users' => ['view_any']],
            'guard' => hexa()->guard(),
        ]);
        $principal->roles()->attach($role->getKey());

        $this->artisan('mcp:provision-principal')
            ->expectsOutputToContain('must have exactly the permissionless Assistant role')
            ->assertFailed();
    }

    public function test_command_refuses_to_provision_without_a_global_assistant_role(): void
    {
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);

        $this->artisan('mcp:provision-principal')
            ->expectsOutputToContain('Create exactly one global Assistant role')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'chatgpt@edventure.ro']);
    }

    public function test_emergency_command_revokes_all_ai_principal_access_and_refresh_tokens(): void
    {
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);
        $principal = User::factory()->create(['name' => 'AI Assistant', 'email' => 'chatgpt@edventure.ro']);

        $client = Client::factory()->create();

        $tokenId = str_repeat('a', 80);
        Passport::token()::query()->create([
            'id' => $tokenId,
            'user_id' => $principal->getKey(),
            'client_id' => $client->getKey(),
            'scopes' => ['mcp:use'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);
        Passport::refreshToken()::query()->create([
            'id' => str_repeat('b', 80),
            'access_token_id' => $tokenId,
            'revoked' => false,
            'expires_at' => now()->addDays(30),
        ]);

        $this->artisan('mcp:revoke-principal-tokens')
            ->expectsOutputToContain('Revoked 1 access token(s) and 1 refresh token(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenId, 'revoked' => true]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['access_token_id' => $tokenId, 'revoked' => true]);
    }

    /** @param array<string, mixed> $access */
    private function createAssistantRole(array $access = []): HexaRole
    {
        return HexaRole::query()->create([
            'name' => 'Assistant',
            'created_by_name' => 'Test',
            'access' => $access,
            'guard' => hexa()->guard(),
        ]);
    }
}
