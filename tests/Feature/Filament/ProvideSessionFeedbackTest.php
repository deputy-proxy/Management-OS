<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Sessions\Actions\ProvideSessionFeedbackAction;
use App\Filament\Resources\Sessions\Pages\ListSessions;
use App\Models\Course;
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

class ProvideSessionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_teacher_can_save_feedback_for_students_enrolled_in_their_session(): void
    {
        Gate::before(fn (): bool => true);
        [$user, $employee, $group, $session] = $this->sessionForTeacher();
        $this->actingAs($user);
        $student = Student::factory()->create();
        $attendance = SessionAttendance::factory()->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
        ]);
        StudentGroupMembership::query()->create(['student_id' => $student->id, 'group_id' => $session->group_id, 'started_on' => $session->starts_at->toDateString()]);

        Livewire::test(ListSessions::class)
            ->callTableAction('provideSessionFeedback', $session, data: [
                'students' => [[
                    'attendance_id' => $attendance->id,
                    'student_name' => $student->full_name,
                    'achievement_badge' => 'excellent',
                    'feedback' => 'Explained the solution clearly.',
                ]],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('session_attendances', [
            'id' => $attendance->id,
            'achievement_badge' => 'excellent',
            'feedback' => 'Explained the solution clearly.',
        ]);

        Livewire::test(ListSessions::class)
            ->callTableAction('provideSessionFeedback', $session, data: [
                'students' => [[
                    'attendance_id' => $attendance->id,
                    'student_name' => $student->full_name,
                    'achievement_badge' => 'good',
                    'feedback' => 'Improved on the second attempt.',
                ]],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('session_attendances', 1);
        $this->assertDatabaseHas('session_attendances', [
            'id' => $attendance->id,
            'achievement_badge' => 'good',
            'feedback' => 'Improved on the second attempt.',
        ]);
    }

    public function test_invalid_achievement_badges_are_not_persisted(): void
    {
        Gate::before(fn (): bool => true);
        [$user, , , $session] = $this->sessionForTeacher();
        $this->actingAs($user);
        $student = Student::factory()->create();
        $attendance = SessionAttendance::factory()->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
        ]);
        StudentGroupMembership::query()->create(['student_id' => $student->id, 'group_id' => $session->group_id, 'started_on' => $session->starts_at->toDateString()]);

        Livewire::test(ListSessions::class)
            ->callTableAction('provideSessionFeedback', $session, data: [
                'students' => [[
                    'attendance_id' => $attendance->id,
                    'student_name' => $student->full_name,
                    'achievement_badge' => 'not-a-badge',
                    'feedback' => 'Invalid badge must not be saved.',
                ]],
            ])
            ->assertHasTableActionErrors();

        $this->assertDatabaseHas('session_attendances', [
            'id' => $attendance->id,
            'achievement_badge' => null,
            'feedback' => null,
        ]);
    }

    public function test_teacher_cannot_manage_a_session_for_another_group(): void
    {
        Gate::before(fn (): bool => true);
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create();
        $otherEmployee = Employee::factory()->create();
        $group = Group::factory()->create(['course_id' => $course->id, 'teacher_id' => $otherEmployee->id]);
        $session = Session::factory()->create([
            'session_type' => 'group',
            'group_id' => $group->id,
            'course_id' => $course->id,
            'teacher_id' => $employee->id,
        ]);
        $this->actingAs($user);

        $this->assertFalse(ProvideSessionFeedbackAction::canManage($session));
    }

    public function test_feedback_rows_cannot_target_a_student_from_another_session(): void
    {
        Gate::before(fn (): bool => true);
        [$user, , , $session] = $this->sessionForTeacher();
        $this->actingAs($user);
        $student = Student::factory()->create();
        $otherSession = Session::factory()->create(['session_type' => 'group']);
        $attendance = SessionAttendance::factory()->create([
            'session_id' => $otherSession->id,
            'student_id' => $student->id,
        ]);

        Livewire::test(ListSessions::class)
            ->callTableAction('provideSessionFeedback', $session, data: [
                'students' => [[
                    'attendance_id' => $attendance->id,
                    'student_name' => $student->full_name,
                    'achievement_badge' => 'excellent',
                    'feedback' => 'Should not save.',
                ]],
            ])
            ->assertHasErrors();

        $this->assertDatabaseMissing('session_attendances', [
            'id' => $attendance->id,
            'feedback' => 'Should not save.',
        ]);
    }

    private function sessionForTeacher(): array
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create();
        $group = Group::factory()->create(['course_id' => $course->id, 'teacher_id' => $employee->id]);
        $session = Session::factory()->create([
            'session_type' => 'group',
            'group_id' => $group->id,
            'course_id' => $course->id,
            'teacher_id' => $employee->id,
        ]);

        return [$user, $employee, $group, $session];
    }
}
