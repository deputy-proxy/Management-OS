<?php

namespace App\Mcp\Server;

use App\Mcp\Contracts\McpCapabilityRegistrar;
use Illuminate\Support\Facades\Log;
use Mcp\Server\Server;
use Mcp\Server\Session\FileSessionLock;
use Mcp\Server\Session\FileSessionStore;
use RuntimeException;

final class McpServerFactory
{
    /**
     * @param  iterable<McpCapabilityRegistrar>  $registrars
     */
    public function __construct(private readonly iterable $registrars) {}

    public function create(): Server
    {
        $logger = Log::channel();
        $sessionPath = (string) config('mcp.session_path');
        $this->ensurePrivateSessionDirectory($sessionPath);

        $builder = Server::builder()
            ->setServerInfo(
                (string) config('mcp.server_name', 'Management-OS'),
                (string) config('mcp.server_version', '0.1.0'),
            )
            ->setLogger($logger)
            ->setLazyLoading(false)
            ->setPaginationLimit((int) config('mcp.pagination_limit', 50))
            ->setSession(
                new FileSessionStore(
                    $sessionPath,
                    (int) config('mcp.session_ttl_seconds', 3600),
                    logger: $logger,
                ),
                gcProbability: 1,
                gcDivisor: 100,
            )
            ->setSessionLock(new FileSessionLock(
                $sessionPath,
                (float) config('mcp.session_lock_timeout_seconds', 5),
                $logger,
            ));

        foreach ($this->registrars as $registrar) {
            $registrar->register($builder);
        }

        return $builder->build();
    }

    private function ensurePrivateSessionDirectory(string $path): void
    {
        if ($path === '') {
            throw new RuntimeException('The MCP session directory is not configured.');
        }

        if (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('The MCP session directory could not be created.');
        }

        if (! is_writable($path) || ! @chmod($path, 0700)) {
            throw new RuntimeException('The MCP session directory must be writable and private.');
        }
    }
}
