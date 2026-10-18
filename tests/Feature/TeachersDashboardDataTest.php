<?php

namespace Tests\Feature;

use App\Filament\Pages\TeachersDashboard;
use App\Models\Course;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Group;
use App\Models\JobDescription;
use App\Models\Session;
use App\Models\User;
use App\Services\TeachersDashboardData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TeachersDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_population_workload_and_assignment_exceptions_use_explicit_roles_and_scheduled_sessions(): void
    {
        $now = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        $teacherRole = JobDescription::factory()->create(['job_title' => 'Teacher']);
        $otherRole = JobDescription::factory()->create(['job_title' => 'Administrator']);
        $teacher = Employee::factory()->create(['full_name' => 'Current Teacher', 'job_description' => $teacherRole->id]);
        $otherEmployee = Employee::factory()->create(['full_name' => 'Non Teacher', 'job_description' => $otherRole->id]);
        $deletedTeacher = Employee::factory()->create(['full_name' => 'Deleted Teacher', 'job_description' => $teacherRole->id]);
        $deletedTeacher->delete();
        $course = Course::factory()->create();

        $teacherGroup = Group::factory()->create(['title' => 'Teacher group', 'course_id' => $course->id, 'teacher_id' => $teacher->id]);
        Group::factory()->create(['title' => 'Non-teacher group', 'course_id' => $course->id, 'teacher_id' => $otherEmployee->id]);
        Group::factory()->create(['title' => 'Deleted teacher group', 'course_id' => $course->id, 'teacher_id' => $deletedTeacher->id]);

        $this->createSession($course, $teacher, '2026-10-18 10:00:00', 'scheduled', Session::TYPE_GROUP, $teacherGroup->id);
        $this->createSession($course, null, '2026-10-17 10:00:00', 'scheduled', Session::TYPE_GROUP, $teacherGroup->id);
        $this->createSession($course, $otherEmployee, '2026-10-16 10:00:00', 'scheduled', Session::TYPE_GROUP, $teacherGroup->id);
        $this->createSession($course, $deletedTeacher, '2026-10-15 10:00:00', 'scheduled', Session::TYPE_GROUP, $teacherGroup->id);
        $this->createSession($course, $teacher, '2026-10-18 11:00:00', 'cancelled', Session::TYPE_GROUP, $teacherGroup->id);
        $this->createSession($course, $teacher, '2026-10-11 23:59:59', 'scheduled', Session::TYPE_GROUP, $teacherGroup->id);
        $this->createSession($course, $teacher, '2026-10-19 00:00:00', 'scheduled', Session::TYPE_GROUP, $teacherGroup->id);

        $summary = app(TeachersDashboardData::class)->summary([
            'employee.index' => true,
            'group.index' => true,
            'session.index' => true,
        ], $now, '7');

        $this->assertSame(2, $summary['teacher_total']);
        $this->assertSame(1, $summary['teacher_current']);
        $this->assertSame(1, $summary['teacher_deleted']);
        $this->assertSame(3, $summary['group_total']);
        $this->assertSame(2, $summary['groups_without_teacher']);
        $this->assertSame(4, $summary['scheduled_sessions']);
        $this->assertSame(3, $summary['assigned_sessions']);
        $this->assertSame(1, $summary['unassigned_sessions']);
        $this->assertSame(2, $summary['sessions_without_teacher_role']);
        $this->assertSame(4, $summary['session_trend']->sum('count'));
        $this->assertSame(1, $summary['teacher_workload']->first()['group_assignments']);
        $this->assertSame(1, $summary['teacher_workload']->first()['scheduled_sessions']);
        $this->assertSame('Current Teacher', $summary['teacher_workload']->first()['name']);
        $this->assertSame('October 18, 2026', $summary['as_of']);
        $this->assertSame(config('app.timezone'), $summary['timezone']);
    }

    public function test_session_metrics_respect_legacy_session_type_permissions(): void
    {
        $teacherRole = JobDescription::factory()->create(['job_title' => 'Teacher']);
        $teacher = Employee::factory()->create(['job_description' => $teacherRole->id]);
        $course = Course::factory()->create();
        $group = Group::factory()->create(['course_id' => $course->id, 'teacher_id' => $teacher->id]);
        $this->createSession($course, $teacher, '2026-10-18 10:00:00', 'scheduled', Session::TYPE_GROUP, $group->id);
        $this->createSession($course, $teacher, '2026-10-18 11:00:00', 'scheduled', Session::TYPE_INDIVIDUAL);

        $summary = app(TeachersDashboardData::class)->summary([
            'group_session.index' => true,
            'individual_session.index' => false,
        ], CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone')), '7');

        $this->assertTrue($summary['can_view_sessions']);
        $this->assertSame(1, $summary['scheduled_sessions']);
        $this->assertSame(1, $summary['session_trend']->sum('count'));
        $this->assertNull($summary['teacher_total']);
        $this->assertCount(0, $summary['teacher_workload']);
    }

    public function test_both_legacy_session_permissions_without_unified_permission_match_the_resource_scope(): void
    {
        $teacherRole = JobDescription::factory()->create(['job_title' => 'Teacher']);
        $teacher = Employee::factory()->create(['job_description' => $teacherRole->id]);
        $course = Course::factory()->create();
        $group = Group::factory()->create(['course_id' => $course->id, 'teacher_id' => $teacher->id]);
        $this->createSession($course, $teacher, '2026-10-18 10:00:00', 'scheduled', Session::TYPE_GROUP, $group->id);

        $summary = app(TeachersDashboardData::class)->summary([
            'group_session.index' => true,
            'individual_session.index' => true,
        ], CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone')), '7');

        $this->assertFalse($summary['can_view_sessions']);
        $this->assertNull($summary['scheduled_sessions']);
        $this->assertCount(0, $summary['session_trend']);
    }

    public function test_session_trend_supports_bounded_periods_and_normalizes_invalid_values(): void
    {
        $now = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        $service = app(TeachersDashboardData::class);
        $permissions = ['session.index' => true];

        foreach (['7' => 7, '30' => 30, '90' => 90] as $period => $days) {
            $summary = $service->summary($permissions, $now, $period);
            $this->assertSame($days, $summary['period_days']);
            $this->assertCount($days, $summary['session_trend']);
        }

        $invalid = $service->summary($permissions, $now, '365');
        $this->assertSame(30, $invalid['period_days']);
        $this->assertSame('Sep 19, 2026', $invalid['range_start']);
        $this->assertSame('Oct 18, 2026', $invalid['range_end']);
    }

    public function test_group_assignment_exceptions_require_employee_visibility(): void
    {
        Group::factory()->create();

        $summary = app(TeachersDashboardData::class)->summary([
            'group.index' => true,
            'employee.index' => false,
        ], CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone')));

        $this->assertSame(1, $summary['group_total']);
        $this->assertNull($summary['groups_without_teacher']);
    }

    public function test_empty_data_returns_zeroes_and_no_permission_does_not_expose_counts(): void
    {
        $now = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        $service = app(TeachersDashboardData::class);
        $empty = $service->summary([
            'employee.index' => true,
            'group.index' => true,
            'session.index' => true,
        ], $now, '7');

        $this->assertSame(0, $empty['teacher_total']);
        $this->assertSame(0, $empty['group_total']);
        $this->assertSame(0, $empty['scheduled_sessions']);
        $this->assertCount(7, $empty['session_trend']);
        $this->assertSame(0, $empty['session_trend']->sum('count'));
        $this->assertCount(0, $empty['teacher_workload']);

        $restricted = $service->summary([], $now, '7');
        $this->assertNull($restricted['teacher_total']);
        $this->assertNull($restricted['scheduled_sessions']);
        $this->assertNull($restricted['group_total']);
        $this->assertCount(0, $restricted['session_trend']);
        $this->assertFalse($restricted['can_view_sessions']);
    }

    public function test_quick_links_require_their_destination_permissions(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => match ($ability) {
            'employee.index', 'group_session.index' => true,
            'employee.create', 'group.index', 'session.index', 'individual_session.index' => false,
            default => null,
        });

        $data = (new TeachersDashboard)->getDashboardData();

        $this->assertNotNull($data['employees_url']);
        $this->assertNull($data['create_employee_url']);
        $this->assertNull($data['groups_url']);
        $this->assertNotNull($data['sessions_url']);
    }

    public function test_dashboard_keeps_its_dedicated_page_permission(): void
    {
        $page = new TeachersDashboard;
        $this->assertSame(
            ['teachers-dashboard.view' => __('Allows viewing the teachers dashboard')],
            $page->defineGates(),
        );

        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'teachers-dashboard.view' ? true : null);
        $this->assertTrue(TeachersDashboard::canAccess());
    }

    public function test_dashboard_denies_access_without_its_dedicated_page_permission(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'teachers-dashboard.view' ? false : null);

        $this->assertFalse(TeachersDashboard::canAccess());
    }

    private function createSession(Course $course, ?Employee $teacher, string $startsAt, string $status, string $type = Session::TYPE_GROUP, ?int $groupId = null): Session
    {
        return Session::factory()->create([
            'session_type' => $type,
            'group_id' => $type === Session::TYPE_GROUP ? $groupId : null,
            'customer_id' => $type === Session::TYPE_INDIVIDUAL ? Customer::factory() : null,
            'course_id' => $course->id,
            'teacher_id' => $teacher?->id,
            'starts_at' => $startsAt,
            'ends_at' => CarbonImmutable::parse($startsAt)->addHour()->toDateTimeString(),
            'status' => $status,
        ]);
    }
}
