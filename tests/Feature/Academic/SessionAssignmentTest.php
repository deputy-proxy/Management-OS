<?php

namespace Tests\Feature\Academic;

use App\Filament\Resources\Sessions\SessionResource;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SessionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_session_must_use_the_groups_course_and_assigned_teacher(): void
    {
        $course = Course::factory()->create();
        $teacher = Employee::factory()->create();
        $group = Group::factory()->create(['course_id' => $course->id, 'teacher_id' => $teacher->id]);

        SessionResource::validateGroupAssignment([
            'session_type' => 'group',
            'group_id' => $group->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
        ]);

        try {
            SessionResource::validateGroupAssignment([
                'session_type' => 'group',
                'group_id' => $group->id,
                'course_id' => $course->id,
                'teacher_id' => Employee::factory()->create()->id,
            ]);
            $this->fail('A group session with a different teacher must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('teacher_id', $exception->errors());
        }

        try {
            SessionResource::validateGroupAssignment([
                'session_type' => 'group',
                'group_id' => $group->id,
                'course_id' => Course::factory()->create()->id,
                'teacher_id' => $teacher->id,
            ]);
            $this->fail('A group session with a different course must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('course_id', $exception->errors());
        }
    }
}
