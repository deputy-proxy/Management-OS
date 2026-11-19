<?php

namespace Tests\Feature;

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

    public function test_command_creates_a_deny_by_default_ai_principal(): void
    {
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);

        $this->artisan('mcp:provision-principal')
            ->expectsOutputToContain('with no roles or panel permissions')
            ->assertSuccessful();

        $principal = User::query()->where('email', 'chatgpt@edventure.ro')->firstOrFail();

        $this->assertSame('AI Assistant', $principal->name);
        $this->assertNull($principal->email_verified_at);
        $this->assertFalse($principal->roles()->exists());
        $this->assertFalse($principal->canAccessPanel(Filament::getCurrentPanel() ?? new Panel));
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

    public function test_command_refuses_to_reuse_an_existing_account_with_roles(): void
    {
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);
        $principal = User::factory()->create(['name' => 'AI Assistant', 'email' => 'chatgpt@edventure.ro']);
        $role = HexaRole::query()->create([
            'name' => 'MCP unexpected role',
            'created_by_name' => 'Test',
            'access' => ['users' => ['view_any']],
            'guard' => hexa()->guard(),
        ]);
        $principal->roles()->attach($role->getKey());

        $this->artisan('mcp:provision-principal')
            ->expectsOutputToContain('already has HexaLite roles')
            ->assertFailed();
    }
}
