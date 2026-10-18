<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckFailedJobs extends Command
{
    protected $signature = 'ops:check-failed-jobs {--max-failed=0 : Maximum acceptable failed-job count}';

    protected $description = 'Check failed-job count without displaying sensitive job payloads.';

    public function handle(): int
    {
        $maxFailed = filter_var($this->option('max-failed'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($maxFailed === false) {
            $this->error('--max-failed must be a non-negative integer.');

            return self::INVALID;
        }

        try {
            if (! Schema::hasTable('failed_jobs')) {
                $this->error('failed-jobs: unavailable');

                return self::FAILURE;
            }

            $count = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            $this->error('failed-jobs: unavailable');

            return self::FAILURE;
        }

        $this->line(sprintf('failed-jobs: %s (count=%d; threshold=%d)', $count <= $maxFailed ? 'healthy' : 'unhealthy', $count, $maxFailed));

        return $count <= $maxFailed ? self::SUCCESS : self::FAILURE;
    }
}
