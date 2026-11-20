<?php

namespace Tests\Feature;

use App\Mcp\Authorization\Scopes\McpGlobalRecordScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyPath = storage_path('framework/testing/passport-keys');
        File::ensureDirectoryExists($this->keyPath, 0700, true);
        $this->ensurePassportKeys();
        Passport::loadKeysFrom($this->keyPath);
    }

    public function test_oauth_discovery_advertises_authorization_code_pkce_and_mcp_scope(): void
    {
        $this->get('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('response_types_supported', ['code'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token'])
            ->assertJsonPath('scopes_supported', ['mcp:use']);

        $this->get('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('scopes_supported', ['mcp:use']);
    }

    public function test_oauth_discovery_advertises_each_registered_tool_scope_on_exact_and_nested_metadata(): void
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

        $scopes = ['mcp:use', 'mcp.finance.read'];

        $this->get('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('scopes_supported', $scopes);

        $this->get('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonPath('scopes_supported', $scopes);

        $this->get('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('scopes_supported', $scopes);
    }

    public function test_authorization_rejects_missing_or_plain_pkce_challenges(): void
    {
        $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => 'public-client',
            'redirect_uri' => 'https://chatgpt.com/connector_platform_oauth_redirect',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'plain',
        ]))->assertBadRequest()->assertJsonPath('error', 'invalid_request');

        $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => 'public-client',
            'redirect_uri' => 'https://chatgpt.com/connector_platform_oauth_redirect',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state',
        ]))->assertBadRequest()->assertJsonPath('error', 'invalid_request');
    }

    public function test_dynamic_registration_allowlists_chatgpt_and_rejects_untrusted_redirects(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT',
            'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
        ])->assertCreated()
            ->assertJsonPath('scope', 'mcp:use')
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('grant_types.0', 'authorization_code');

        $this->postJson('/oauth/register', [
            'client_name' => 'Untrusted client',
            'redirect_uris' => ['https://attacker.example/callback'],
        ])->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_public_client_completes_pkce_exchange_refresh_rotation_and_revocation(): void
    {
        $registration = $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT PKCE test',
            'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
        ])->assertCreated();

        $clientId = (string) $registration->json('client_id');
        $user = User::factory()->create();
        $principal = User::factory()->create(['name' => 'AI Assistant', 'email' => 'chatgpt@edventure.ro']);
        config(['mcp.ai_principal_email' => 'chatgpt@edventure.ro']);
        $this->actingAs($user, 'web');

        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $redirectUri = 'https://chatgpt.com/connector_platform_oauth_redirect';

        $authorization = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'resource' => url('/mcp'),
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'management-os-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))->assertOk();

        preg_match('/name="auth_token" value="([^"]+)"/', $authorization->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $approval = $this->post(route('passport.authorizations.approve'), [
            'client_id' => $clientId,
            'auth_token' => $matches[1],
        ])->assertRedirect();

        parse_str((string) parse_url((string) $approval->headers->get('Location'), PHP_URL_QUERY), $redirectQuery);
        $this->assertSame('management-os-state', $redirectQuery['state'] ?? null);

        $tokenRequest = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'resource' => url('/mcp'),
            'code' => $redirectQuery['code'],
        ];

        $this->postJson('/oauth/token', [...$tokenRequest, 'code_verifier' => str_repeat('wrong', 13)])
            ->assertStatus(400);

        $token = $this->postJson('/oauth/token', [...$tokenRequest, 'code_verifier' => $verifier])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'token_type', 'resource'])
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('expires_in', 3600);

        $jwt = (new Parser(new JoseEncoder))->parse($token->json('access_token'));
        $this->assertSame([$clientId, url('/mcp')], $jwt->claims()->get('aud'));
        $this->assertSame(url('/'), $jwt->claims()->get('iss'));
        $this->assertSame((string) $principal->getKey(), $jwt->claims()->get('sub'));
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $jwt->claims()->get('jti'),
            'user_id' => $principal->getKey(),
        ]);

        $this->postJson('/oauth/token', [...$tokenRequest, 'code_verifier' => $verifier])
            ->assertStatus(400);

        $refreshToken = $token->json('refresh_token');
        $refreshed = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'resource' => url('/mcp'),
            'client_id' => $clientId,
            'refresh_token' => $refreshToken,
        ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'token_type', 'resource'])
            ->assertJsonPath('resource', url('/mcp'));

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'resource' => url('/mcp'),
            'client_id' => $clientId,
            'refresh_token' => $refreshToken,
        ])->assertStatus(400);

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'oauth-pkce-test', 'version' => '1.0.0'],
            ],
        ], [
            'Authorization' => 'Bearer '.$refreshed->json('access_token'),
            'Accept' => 'application/json, text/event-stream',
        ])->assertOk()->assertJsonPath('result.serverInfo.name', 'Management-OS');

        Passport::tokenModel()::query()
            ->where('user_id', $principal->getKey())
            ->where('client_id', $clientId)
            ->update(['revoked' => true]);
        Auth::forgetGuards();

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], [
            'Authorization' => 'Bearer '.$refreshed->json('access_token'),
            'Accept' => 'application/json, text/event-stream',
        ])->assertUnauthorized();
    }

    public function test_oauth_authorization_and_token_requests_reject_wrong_or_missing_resource(): void
    {
        $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => 'public-client',
            'redirect_uri' => 'https://chatgpt.com/connector_platform_oauth_redirect',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))->assertBadRequest()->assertJsonPath('error', 'invalid_target');

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'public-client',
            'resource' => 'https://attacker.example/mcp',
        ])->assertBadRequest()->assertJsonPath('error', 'invalid_target');
    }

    private function ensurePassportKeys(): void
    {
        $privateKeyPath = $this->keyPath.'/oauth-private.key';
        $publicKeyPath = $this->keyPath.'/oauth-public.key';

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
}
