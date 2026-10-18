<?php

namespace Tests\Feature\Academic;

use App\Models\Session;
use App\Services\Academic\SessionLifecycleService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SessionLifecycleServiceTest extends TestCase
{
    public function test_new_sessions_must_start_scheduled(): void
    {
        $service = app(SessionLifecycleService::class);
        $service->validateCreation(['status' => 'scheduled']);

        $this->expectException(ValidationException::class);
        $service->validateCreation(['status' => 'completed']);
    }

    public function test_scheduled_sessions_can_be_completed_or_cancelled_but_terminal_sessions_cannot_be_reopened(): void
    {
        $service = app(SessionLifecycleService::class);
        $scheduled = new Session(['status' => 'scheduled', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);

        $service->validateUpdate($scheduled, ['status' => 'completed', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);
        $service->validateUpdate($scheduled, ['status' => 'cancelled', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);

        $completed = new Session(['status' => 'completed', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);
        try {
            $service->validateUpdate($completed, ['status' => 'scheduled', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);
            $this->fail('Completed sessions must be terminal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $service->validateUpdate($scheduled, ['status' => 'scheduled', 'starts_at' => '2026-10-21 10:00:00', 'ends_at' => '2026-10-21 11:00:00']);
    }

    public function test_cancelled_session_times_are_immutable(): void
    {
        $service = app(SessionLifecycleService::class);
        $cancelled = new Session(['status' => 'cancelled', 'starts_at' => '2026-10-20 10:00:00', 'ends_at' => '2026-10-20 11:00:00']);

        $this->expectException(ValidationException::class);
        $service->validateUpdate($cancelled, ['status' => 'cancelled', 'starts_at' => '2026-10-21 10:00:00', 'ends_at' => '2026-10-21 11:00:00']);
    }
}
