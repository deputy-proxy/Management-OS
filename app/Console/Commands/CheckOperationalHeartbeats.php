<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckOperationalHeartbeats extends Command
{
    protected $signature = 'ops:check-heartbeats {--max-age=180 : Maximum acceptable heartbeat age in seconds}';

    protected $description = 'Check database connectivity and recent queue-worker and scheduler heartbeats.';

    public function handle(): int
    {
        $maxAge = filter_var($this->option('max-age'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($maxAge === false) {
            $this->error('--max-age must be a positive integer.');

            return self::INVALID;
        }

        $failed = false;
        $this->check('database', function (): void {
            DB::connection()->select('SELECT 1');
        }, $failed);
        $this->checkHeartbeat('queue-worker', 'ops:heartbeat:queue-worker', $maxAge, $failed);
        $this->checkHeartbeat('scheduler', 'ops:heartbeat:scheduler', $maxAge, $failed);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function checkHeartbeat(string $name, string $key, int $maxAge, bool &$failed): void
    {
        $this->check($name, function () use ($key, $maxAge): void {
            $lastSeen = Cache::get($key);
            if (! is_int($lastSeen) || now()->timestamp - $lastSeen > $maxAge || $lastSeen > now()->timestamp + 30) {
                throw new \RuntimeException('Heartbeat missing or stale.');
            }
        }, $failed);
    }

    private function check(string $name, callable $probe, bool &$failed): void
    {
        try {
            $probe();
            $this->line($name.': healthy');
        } catch (Throwable $exception) {
            $failed = true;
            $this->line($name.': unhealthy');
        }
    }
}
