<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Sessions\Actions\InputSessionAttendanceAction;
use App\Filament\Resources\Sessions\Pages\ListSessions;
use App\Models\Course;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Session;
use App\Models\SessionAttendance;
use App\Models\Student;
use App\Models\StudentGroupMembership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class InputSessionAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_teacher_can_record_and_correct_group_session_attendance_without_duplicate_rows(): void
    {
        Gate::before(fn (): bool => true);
        [$user, $session] = $this->groupSessionForTeacher();
        $this->actingAs($user);
        $student = Student::factory()->create();
        $attendance = SessionAttendance::factory()->create(['session_id' => $session->id, 'student_id' => $student->id]);
        StudentGroupMembership::query()->create(['student_id' => $student->id, 'group_id' => $session->group_id, 'started_on' => $session->starts_at->toDateString()]);

        Livewire::test(ListSessions::class)
            ->callTableAction('inputSessionAttendance', $session, data: ['students' => [[
                'attendance_id' => $attendance->id,
                'student_name' => $student->full_name,
                'status' => 'present',
            ]]])
            ->assertHasNoTableActionErrors();

        Livewire::test(ListSessions::class)
            ->callTableAction('inputSessionAttendance', $session, data: ['students' => [[
                'attendance_id' => $attendance->id,
                'student_name' => $student->full_name,
                'status' => 'excused',
            ]]])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('session_attendances', 1);
        $this->assertDatabaseHas('session_attendances', ['id' => $attendance->id, 'status' => 'excused']);
    }

    public function test_group_attendance_cannot_update_rows_from_another_session(): void
    {
        Gate::before(fn (): bool => true);
        [$user, $session] = $this->groupSessionForTeacher();
        $this->actingAs($user);
        $otherSession = Session::factory()->create(['session_type' => 'group']);
        $student = Student::factory()->create();
        $attendance = SessionAttendance::factory()->create(['session_id' => $otherSession->id, 'student_id' => $student->id]);

        Livewire::test(ListSessions::class)
            ->callTableAction('inputSessionAttendance', $session, data: ['students' => [[
                'attendance_id' => $attendance->id,
                'student_name' => $student->full_name,
                'status' => 'present',
            ]]])
            ->assertHasTableActionErrors();

        $this->assertDatabaseHas('session_attendances', ['id' => $attendance->id, 'status' => null]);
    }

    public function test_group_attendance_rejects_an_existing_row_for_a_student_not_enrolled_on_the_session_date(): void
    {
        Gate::before(fn (): bool => true);
        [$user, $session] = $this->groupSessionForTeacher();
        $this->actingAs($user);
        $student = Student::factory()->create();
        $attendance = SessionAttendance::factory()->create(['session_id' => $session->id, 'student_id' => $student->id]);

        Livewire::test(ListSessions::class)
            ->callTableAction('inputSessionAttendance', $session, data: ['students' => [[
                'attendance_id' => $attendance->id,
                'student_name' => $student->full_name,
                'status' => 'present',
            ]]])
            ->assertHasTableActionErrors();

        $this->assertDatabaseHas('session_attendances', ['id' => $attendance->id, 'status' => null]);
    }

    public function test_individual_session_attendance_is_saved_on_the_existing_session(): void
    {
        Gate::before(fn (): bool => true);
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create();
        $customer = Customer::factory()->create();
        $session = Session::factory()->individual()->create([
            'customer_id' => $customer->id,
            'course_id' => $course->id,
            'teacher_id' => $employee->id,
            'attendance_status' => null,
        ]);
        $this->actingAs($user);

        Livewire::test(ListSessions::class)
            ->callTableAction('inputSessionAttendance', $session, data: [
                'customer_name' => $customer->name,
                'attendance_status' => 'absent',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('scheduled_sessions', ['id' => $session->id, 'session_type' => 'individual', 'attendance_status' => 'absent']);
        $this->assertDatabaseCount('scheduled_sessions', 1);
    }

    public function test_invalid_group_attendance_status_is_rejected(): void
    {
        Gate::before(fn (): bool => true);
        [$user, $session] = $this->groupSessionForTeacher();
        $this->actingAs($user);
        $student = Student::factory()->create();
        $attendance = SessionAttendance::factory()->create(['session_id' => $session->id, 'student_id' => $student->id]);
        StudentGroupMembership::query()->create(['student_id' => $student->id, 'group_id' => $session->group_id, 'started_on' => $session->starts_at->toDateString()]);

        Livewire::test(ListSessions::class)
            ->callTableAction('inputSessionAttendance', $session, data: ['students' => [[
                'attendance_id' => $attendance->id,
                'student_name' => $student->full_name,
                'status' => 'late-without-definition',
            ]]])
            ->assertHasTableActionErrors();

        $this->assertDatabaseHas('session_attendances', ['id' => $attendance->id, 'status' => null]);
    }

    public function test_attendance_actions_require_the_matching_update_permission(): void
    {
        Gate::before(fn ($user, string $ability): bool => ! in_array($ability, ['session.update', 'group_session.update', 'individual_session.update'], true));
        [$user, $groupSession] = $this->groupSessionForTeacher();
        $employee = Employee::query()->where('user_id', $user->id)->firstOrFail();
        $individualSession = Session::factory()->individual()->create(['teacher_id' => $employee->id]);
        $this->actingAs($user);

        $this->assertFalse(InputSessionAttendanceAction::canManage($groupSession));
        $this->assertFalse(InputSessionAttendanceAction::canManage($individualSession));
    }

    public function test_unified_session_update_permission_allows_the_assigned_teacher_to_record_attendance(): void
    {
        Gate::before(fn ($user, string $ability): bool => $ability === 'session.update');
        [$user, $session] = $this->groupSessionForTeacher();
        $this->actingAs($user);

        $this->assertTrue(InputSessionAttendanceAction::canManage($session));

        $individualSession = Session::factory()->individual()->create([
            'teacher_id' => Employee::query()->where('user_id', $user->id)->value('id'),
        ]);

        $this->assertTrue(InputSessionAttendanceAction::canManage($individualSession));
    }

    public function test_group_attendance_action_requires_the_assigned_teacher(): void
    {
        Gate::before(fn (): bool => true);
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $otherTeacher = Employee::factory()->create();
        $course = Course::factory()->create();
        $group = Group::factory()->create(['course_id' => $course->id, 'teacher_id' => $otherTeacher->id]);
        $session = Session::factory()->create(['session_type' => 'group', 'group_id' => $group->id, 'course_id' => $course->id, 'teacher_id' => $employee->id]);
        $this->actingAs($user);

        $this->assertFalse(InputSessionAttendanceAction::canManage($session));
    }

    /** @return array{User, Session} */
    private function groupSessionForTeacher(): array
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create();
        $group = Group::factory()->create(['course_id' => $course->id, 'teacher_id' => $employee->id]);
        $session = Session::factory()->create(['session_type' => 'group', 'group_id' => $group->id, 'course_id' => $course->id, 'teacher_id' => $employee->id]);

        return [$user, $session];
    }
}
