<?php

namespace Tests\Feature\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthAndObservabilityTest extends TestCase
{
    public function test_readiness_returns_a_minimal_success_response_when_database_is_available(): void
    {
        $this->getJson('/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
    }

    public function test_readiness_hides_database_failure_details(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('secret host and credentials'));

        $this->getJson('/ready')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'not_ready'])
            ->assertDontSee('secret host and credentials');
    }

    public function test_request_id_is_returned_and_invalid_incoming_value_is_replaced(): void
    {
        $this->withHeader('X-Request-ID', 'external-trace_123')
            ->get('/ready')
            ->assertHeader('X-Request-ID', 'external-trace_123');

        $response = $this->withHeader('X-Request-ID', "invalid\r\nX-Leak: yes")->get('/ready');
        $response->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $response->headers->get('X-Request-ID'));
        $this->assertNull($response->headers->get('X-Leak'));
    }

    public function test_operational_heartbeat_check_passes_with_recent_queue_and_scheduler_heartbeats(): void
    {
        Cache::put('ops:heartbeat:queue-worker', now()->timestamp, now()->addMinutes(5));
        Cache::put('ops:heartbeat:scheduler', now()->timestamp, now()->addMinutes(5));

        $this->artisan('ops:check-heartbeats')
            ->expectsOutput('database: healthy')
            ->expectsOutput('queue-worker: healthy')
            ->expectsOutput('scheduler: healthy')
            ->assertExitCode(0);
    }

    public function test_operational_heartbeat_check_fails_when_heartbeats_are_missing_or_stale(): void
    {
        Cache::put('ops:heartbeat:queue-worker', now()->subMinutes(10)->timestamp, now()->addMinutes(5));

        $this->artisan('ops:check-heartbeats')
            ->expectsOutput('database: healthy')
            ->expectsOutput('queue-worker: unhealthy')
            ->expectsOutput('scheduler: unhealthy')
            ->assertExitCode(1);
    }

    public function test_backup_verification_rejects_missing_and_empty_files(): void
    {
        $this->artisan('ops:verify-backup', ['path' => sys_get_temp_dir().'/management-os-missing-backup.sql'])
            ->assertExitCode(1);

        $path = tempnam(sys_get_temp_dir(), 'management-os-empty-backup-');
        try {
            $this->artisan('ops:verify-backup', ['path' => $path])->assertExitCode(1);
        } finally {
            @unlink($path);
        }
    }

    public function test_backup_verification_reports_digest_but_disclaims_restore_validation(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'management-os-backup-');
        file_put_contents($path, 'non-secret backup fixture');

        try {
            $this->artisan('ops:verify-backup', ['path' => $path])
                ->expectsOutput('Backup artifact is readable and non-empty.')
                ->expectsOutput('sha256: '.hash_file('sha256', $path))
                ->expectsOutputToContain('does not prove that the backup can be restored')
                ->assertExitCode(0);
        } finally {
            @unlink($path);
        }
    }
}
