<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyBackupArtifact extends Command
{
    protected $signature = 'ops:verify-backup {path : Path to a backup artifact} {--sha256= : Expected SHA-256 from a separately trusted manifest}';

    protected $description = 'Check that a backup artifact is readable, non-empty, and has a SHA-256 digest.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $expectedDigest = $this->option('sha256');

        if ($expectedDigest !== null && $expectedDigest !== '') {
            if (preg_match('/\A[a-f0-9]{64}\z/i', $expectedDigest) !== 1) {
                $this->error('--sha256 must be a 64-character hexadecimal SHA-256 digest from a trusted manifest.');

                return self::INVALID;
            }

            $expectedDigest = strtolower($expectedDigest);
        }

        try {
            if (! is_file($path) || ! is_readable($path) || filesize($path) === false || filesize($path) === 0) {
                $this->error('Backup verification failed: artifact is missing, unreadable, or empty.');

                return self::FAILURE;
            }

            $digest = hash_file('sha256', $path);
            if (! is_string($digest)) {
                $this->error('Backup verification failed: artifact could not be hashed.');

                return self::FAILURE;
            }

            if (is_string($expectedDigest) && ! hash_equals($expectedDigest, $digest)) {
                $this->error('Backup verification failed: SHA-256 does not match the trusted manifest.');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            Log::warning('Backup artifact verification failed', ['exception_class' => $exception::class]);
            $this->error('Backup verification failed. See restricted operational logs for details.');

            return self::FAILURE;
        }

        $this->info('Backup artifact is readable and non-empty.');
        $this->line('sha256: '.$digest);
        if (is_string($expectedDigest)) {
            $this->info('SHA-256 matches the supplied trusted manifest digest.');
        }
        $this->warn('This check does not prove that the backup can be restored. Perform the documented isolated restore drill.');

        return self::SUCCESS;
    }
}
