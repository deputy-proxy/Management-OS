<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Models\ProgressUpdate;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentGroupMembership;
use App\Models\TestSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicSessionLinkValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_and_assessment_records_can_link_to_a_student_eligible_for_the_group_session(): void
    {
        [$session, $student] = $this->sessionAndStudent(true);

        $progress = ProgressUpdate::factory()->create([
            'student_id' => $student->id,
            'session_id' => $session->id,
        ]);
        $submission = TestSubmission::factory()->create([
            'student_id' => $student->id,
            'session_id' => $session->id,
        ]);

        $this->assertSame($session->id, $progress->session->id);
        $this->assertSame($session->id, $submission->session->id);
        $this->assertDatabaseHas('progress_updates', ['id' => $progress->id, 'session_id' => $session->id]);
        $this->assertDatabaseHas('test_submissions', ['id' => $submission->id, 'session_id' => $session->id]);
    }

    public function test_progress_and_assessment_records_reject_a_student_not_eligible_on_the_session_date(): void
    {
        [$session, $student] = $this->sessionAndStudent(false);

        try {
            ProgressUpdate::factory()->create(['student_id' => $student->id, 'session_id' => $session->id]);
            $this->fail('Progress updates must not be linked to a session where the student was not a member.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_id', $exception->errors());
        }

        try {
            TestSubmission::factory()->create(['student_id' => $student->id, 'session_id' => $session->id]);
            $this->fail('Assessment submissions must not be linked to a session where the student was not a member.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_id', $exception->errors());
        }
    }

    /** @return array{Session, Student} */
    private function sessionAndStudent(bool $eligible): array
    {
        $group = Group::factory()->create();
        $session = Session::factory()->create([
            'session_type' => Session::TYPE_GROUP,
            'group_id' => $group->id,
            'starts_at' => '2026-10-18 10:00:00',
            'ends_at' => '2026-10-18 11:00:00',
        ]);
        $student = Student::factory()->create();

        if ($eligible) {
            StudentGroupMembership::query()->create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'started_on' => '2026-10-18',
            ]);
        }

        return [$session, $student];
    }
}
