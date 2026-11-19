<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\TestCase;

class McpTransportTest extends TestCase
{
    private string $sessionPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionPath = storage_path('framework/testing/mcp-sessions');
        config()->set('mcp.session_path', $this->sessionPath);
        File::deleteDirectory($this->sessionPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessionPath);

        parent::tearDown();
    }

    public function test_streamable_http_initializes_a_session_and_advertises_management_os(): void
    {
        $response = $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass,
                'clientInfo' => [
                    'name' => 'mcp-transport-test',
                    'version' => '1.0.0',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('result.serverInfo.name', 'Management-OS')
            ->assertJsonPath('result.serverInfo.version', '0.1.0');

        $this->assertNotEmpty($response->headers->get('Mcp-Session-Id'));
        $this->assertSame(0700, fileperms($this->sessionPath) & 0777);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function test_modern_stateless_protocol_discovery_is_supported(): void
    {
        $response = $this->call('POST', '/mcp', [], [], [], [
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            'HTTP_MCP_METHOD' => 'server/discover',
            'CONTENT_TYPE' => 'application/json',
        ], '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}');

        $response->assertOk()
            ->assertJsonPath('result.supportedVersions.0', '2026-07-28')
            ->assertJsonMissingPath('result.capabilities.tools')
            ->assertJsonMissingPath('result.capabilities.resources')
            ->assertJsonMissingPath('result.capabilities.prompts');
    }

    public function test_unauthenticated_session_discovers_no_business_tools_resources_or_prompts(): void
    {
        $sessionId = $this->initializeSession();

        $this->mcpPost([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], $sessionId)->assertAccepted();

        $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => new \stdClass,
        ], $sessionId)->assertOk()->assertJsonPath('result.tools', []);

        $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'resources/list',
            'params' => new \stdClass,
        ], $sessionId)->assertOk()->assertJsonPath('result.resources', []);

        $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'prompts/list',
            'params' => new \stdClass,
        ], $sessionId)->assertOk()->assertJsonPath('result.prompts', []);
    }

    public function test_cors_preflight_allows_only_configured_origins(): void
    {
        config()->set('mcp.allowed_origins', ['https://trusted.example']);

        $response = $this->call('OPTIONS', '/mcp', [], [], [], [
            'HTTP_HOST' => 'localhost',
            'HTTP_ORIGIN' => 'https://trusted.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type,MCP-Protocol-Version,Mcp-Method',
        ]);

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://trusted.example');
    }

    public function test_unconfigured_cross_origin_requests_are_rejected(): void
    {
        config()->set('mcp.allowed_origins', ['https://trusted.example']);

        $response = $this->call('OPTIONS', '/mcp', [], [], [], [
            'HTTP_HOST' => 'localhost',
            'HTTP_ORIGIN' => 'https://attacker.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $response->assertForbidden();
    }

    public function test_endpoint_is_rate_limited_per_source_ip(): void
    {
        for ($request = 0; $request < 60; $request++) {
            $this->call('OPTIONS', '/mcp')->assertNoContent();
        }

        $this->call('OPTIONS', '/mcp')->assertTooManyRequests();
    }

    public function test_unsupported_http_methods_return_a_safe_method_not_allowed_response(): void
    {
        $response = $this->call('PUT', '/mcp', [], [], [], [
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        $response->assertStatus(405)->assertJsonPath('error.code', -32600);
        $this->assertStringNotContainsString(base_path(), (string) $response->getContent());
    }

    public function test_untrusted_host_headers_are_rejected_before_protocol_processing(): void
    {
        $factory = new Psr17Factory;
        $request = $factory->createServerRequest('POST', 'https://attacker.example/mcp')
            ->withHeader('Host', 'attacker.example');
        $handler = new class($factory) implements RequestHandlerInterface
        {
            public function __construct(private readonly ResponseFactoryInterface $factory) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->factory->createResponse(200);
            }
        };

        $response = (new DnsRebindingProtectionMiddleware(
            allowedHosts: ['management.example.com'],
            responseFactory: $factory,
            streamFactory: $factory,
        ))->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_malformed_json_is_rejected_without_exposing_internal_details(): void
    {
        $response = $this->mcpPost('{not-json');

        $response->assertStatus(400)->assertJsonPath('error.code', -32700);
        $this->assertStringNotContainsString(base_path(), (string) $response->getContent());
    }

    public function test_request_bodies_are_bounded_by_the_configured_limit(): void
    {
        config()->set('mcp.max_body_bytes', 64);

        $response = $this->mcpPost([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass,
                'clientInfo' => [
                    'name' => str_repeat('x', 200),
                    'version' => '1.0.0',
                ],
            ],
        ]);

        $response->assertStatus(413);
        $this->assertStringNotContainsString(base_path(), (string) $response->getContent());
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
                'clientInfo' => [
                    'name' => 'mcp-transport-test',
                    'version' => '1.0.0',
                ],
            ],
        ]);

        $response->assertOk();
        $sessionId = $response->headers->get('Mcp-Session-Id');
        $this->assertNotEmpty($sessionId);

        return (string) $sessionId;
    }

    /**
     * @param  array<string, mixed>|string  $payload
     */
    private function mcpPost(array|string $payload, ?string $sessionId = null): TestResponse
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'CONTENT_TYPE' => 'application/json',
        ];

        if ($sessionId !== null) {
            $server['HTTP_MCP_SESSION_ID'] = $sessionId;
        }

        $content = is_array($payload) ? json_encode($payload, JSON_THROW_ON_ERROR) : $payload;

        return $this->call('POST', '/mcp', [], [], [], $server, $content);
    }
}
