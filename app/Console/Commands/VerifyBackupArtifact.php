<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyBackupArtifact extends Command
{
    protected $signature = 'ops:verify-backup {path : Path to a backup artifact}';

    protected $description = 'Check that a backup artifact is readable, non-empty, and has a SHA-256 digest.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

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
        } catch (Throwable $exception) {
            Log::warning('Backup artifact verification failed', ['exception_class' => $exception::class]);
            $this->error('Backup verification failed. See restricted operational logs for details.');

            return self::FAILURE;
        }

        $this->info('Backup artifact is readable and non-empty.');
        $this->line('sha256: '.$digest);
        $this->warn('This check does not prove that the backup can be restored. Perform the documented isolated restore drill.');

        return self::SUCCESS;
    }
}
