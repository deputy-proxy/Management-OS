<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Models\Student;
use App\Models\StudentGroupMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentGroupMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_records_link_students_and_groups_with_effective_dates(): void
    {
        $student = Student::factory()->create();
        $group = Group::factory()->create();
        $membership = StudentGroupMembership::query()->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'started_on' => '2026-01-10',
            'ended_on' => '2026-03-31',
        ]);

        $this->assertSame($student->id, $membership->student->id);
        $this->assertSame($group->id, $membership->group->id);
        $this->assertTrue($membership->isActiveOn('2026-02-01'));
        $this->assertFalse($membership->isActiveOn('2026-01-09'));
        $this->assertFalse($membership->isActiveOn('2026-04-01'));
    }

    public function test_a_student_can_move_groups_without_erasing_prior_membership(): void
    {
        $student = Student::factory()->create();
        $firstGroup = Group::factory()->create();
        $secondGroup = Group::factory()->create();

        $previous = StudentGroupMembership::query()->create([
            'student_id' => $student->id,
            'group_id' => $firstGroup->id,
            'started_on' => '2026-01-10',
            'ended_on' => '2026-03-31',
        ]);
        $current = StudentGroupMembership::query()->create([
            'student_id' => $student->id,
            'group_id' => $secondGroup->id,
            'started_on' => '2026-04-01',
        ]);

        $this->assertSame('2026-03-31', $previous->fresh()->ended_on->toDateString());
        $this->assertNull($current->fresh()->ended_on);
        $this->assertCount(2, $student->fresh()->groupMemberships);
    }

    public function test_database_rejects_duplicate_active_membership_for_same_student_and_group(): void
    {
        $student = Student::factory()->create();
        $group = Group::factory()->create();
        $attributes = [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'started_on' => '2026-01-10',
        ];

        StudentGroupMembership::query()->create($attributes);

        $this->expectException(QueryException::class);
        StudentGroupMembership::query()->create($attributes);
    }

    public function test_ended_membership_can_be_replaced_without_deleting_history(): void
    {
        $student = Student::factory()->create();
        $group = Group::factory()->create();
        $previous = StudentGroupMembership::query()->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'started_on' => '2026-01-10',
            'ended_on' => '2026-03-31',
        ]);
        $current = StudentGroupMembership::query()->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'started_on' => '2026-04-01',
        ]);

        $this->assertNotSame($previous->id, $current->id);
        $this->assertDatabaseCount('student_group_memberships', 2);
    }
}