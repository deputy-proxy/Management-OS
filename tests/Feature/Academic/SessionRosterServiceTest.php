<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Models\Session;
use App\Models\SessionAttendance;
use App\Models\Student;
use App\Models\StudentGroupMembership;
use App\Services\Academic\SessionRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionRosterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_uses_membership_effective_on_session_date_and_creates_missing_attendance_once(): void
    {
        $group = Group::factory()->create();
        $session = Session::factory()->create([
            'session_type' => Session::TYPE_GROUP,
            'group_id' => $group->id,
            'starts_at' => '2026-04-15 10:00:00',
            'ends_at' => '2026-04-15 11:00:00',
        ]);
        $eligible = Student::factory()->create();
        $notYetEnrolled = Student::factory()->create();
        $alreadyLeft = Student::factory()->create();

        StudentGroupMembership::query()->create(['student_id' => $eligible->id, 'group_id' => $group->id, 'started_on' => '2026-04-15']);
        StudentGroupMembership::query()->create(['student_id' => $notYetEnrolled->id, 'group_id' => $group->id, 'started_on' => '2026-04-16']);
        StudentGroupMembership::query()->create(['student_id' => $alreadyLeft->id, 'group_id' => $group->id, 'started_on' => '2026-01-01', 'ended_on' => '2026-04-14']);

        $service = app(SessionRosterService::class);
        $this->assertSame([$eligible->id], $service->eligibleStudentIds($session));

        $service->synchronize($session);
        $service->synchronize($session);

        $this->assertDatabaseCount('session_attendances', 1);
        $this->assertDatabaseHas('session_attendances', ['session_id' => $session->id, 'student_id' => $eligible->id]);
    }

    public function test_roster_sync_preserves_existing_historical_attendance_for_non_members(): void
    {
        $group = Group::factory()->create();
        $session = Session::factory()->create([
            'session_type' => Session::TYPE_GROUP,
            'group_id' => $group->id,
            'starts_at' => '2026-04-15 10:00:00',
            'ends_at' => '2026-04-15 11:00:00',
        ]);
        $student = Student::factory()->create();
        $historical = SessionAttendance::factory()->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        app(SessionRosterService::class)->synchronize($session);

        $this->assertDatabaseHas('session_attendances', ['id' => $historical->id, 'status' => 'present']);
        $this->assertSame([], app(SessionRosterService::class)->eligibleStudentIds($session));
    }
}
