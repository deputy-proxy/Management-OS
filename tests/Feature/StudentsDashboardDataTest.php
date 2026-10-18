<?php

namespace Tests\Feature;

use App\Filament\Pages\StudentsDashboard;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentsDashboardData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class StudentsDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_separates_current_and_soft_deleted_records_and_fills_enrollment_days(): void
    {
        $now = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        Student::factory()->create(['full_name' => 'Boundary Student', 'created_at' => '2026-10-12 00:00:00']);
        Student::factory()->create(['full_name' => 'Outside Student', 'created_at' => '2026-10-11 23:59:59']);
        Student::factory()->create(['full_name' => 'Deleted Student', 'created_at' => '2026-10-18 09:00:00'])->delete();
        Student::factory()->create(['full_name' => 'After Window Student', 'created_at' => '2026-10-19 00:00:00']);

        $summary = app(StudentsDashboardData::class)->summary($now, period: '7');

        $this->assertSame(4, $summary['total_students']);
        $this->assertSame(3, $summary['current_students']);
        $this->assertSame(1, $summary['deleted_students']);
        $this->assertSame('October 18, 2026', $summary['as_of']);
        $this->assertSame(config('app.timezone'), $summary['timezone']);
        $this->assertCount(7, $summary['enrollment_trend']);
        $this->assertSame(1, $summary['enrollment_trend']->firstWhere('date', '2026-10-12')['count']);
        $this->assertSame(1, $summary['enrollment_trend']->firstWhere('date', '2026-10-18')['count']);
        $this->assertSame(2, $summary['enrollment_trend']->sum('count'));
        $this->assertSame(['Boundary Student'], $summary['recent_students']->pluck('full_name')->all());
    }

    public function test_enrollment_trend_supports_7_30_and_90_day_ranges_and_normalizes_invalid_periods(): void
    {
        $now = CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone'));
        $service = app(StudentsDashboardData::class);

        $this->assertCount(7, $service->summary($now, period: '7')['enrollment_trend']);
        $this->assertCount(30, $service->summary($now, period: '30')['enrollment_trend']);
        $this->assertCount(90, $service->summary($now, period: '90')['enrollment_trend']);
        $invalid = $service->summary($now, period: '365');
        $this->assertSame(30, $invalid['period_days']);
        $this->assertSame('Sep 19, 2026', $invalid['range_start']);
        $this->assertSame('Oct 18, 2026', $invalid['range_end']);
    }

    public function test_overdue_student_task_queue_respects_task_scope_and_excludes_completed_or_deleted_students(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($viewer);
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);
        $student = Student::factory()->create(['full_name' => 'Visible Student']);
        $otherStudent = Student::factory()->create(['full_name' => 'Other Student']);
        $deletedStudent = Student::factory()->create(['full_name' => 'Deleted Student']);
        $deletedStudent->delete();

        $student->tasks()->create([
            'action' => 'Visible overdue follow-up', 'description' => 'Follow up', 'assigned_to' => $viewer->id,
            'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'pending', 'due_date' => '2026-10-17',
        ]);
        $this->actingAs($other);
        $otherStudent->tasks()->create([
            'action' => 'Hidden overdue follow-up', 'description' => 'Follow up', 'assigned_to' => $other->id,
            'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'pending', 'due_date' => '2026-10-16',
        ]);
        $this->actingAs($viewer);
        $student->tasks()->create([
            'action' => 'Completed follow-up', 'description' => 'Done', 'assigned_to' => $viewer->id,
            'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'done', 'due_date' => '2026-10-16',
        ]);
        $deletedStudent->tasks()->create([
            'action' => 'Deleted student follow-up', 'description' => 'Deleted', 'assigned_to' => $viewer->id,
            'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'pending', 'due_date' => '2026-10-16',
        ]);

        $summary = app(StudentsDashboardData::class)->summary(
            CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone')),
            canViewStudents: true,
            canViewTasks: true,
            canViewAllTasks: false,
        );

        $this->assertSame(['Visible overdue follow-up'], $summary['overdue_student_tasks']->pluck('action')->all());
        $this->assertSame('Visible Student', $summary['overdue_student_tasks']->first()->taskable->full_name);
    }

    public function test_empty_population_returns_real_zeroes_and_zero_filled_trend(): void
    {
        $summary = app(StudentsDashboardData::class)->summary(
            CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone')),
            canViewStudents: true,
        );

        $this->assertSame(0, $summary['total_students']);
        $this->assertSame(0, $summary['current_students']);
        $this->assertSame(0, $summary['deleted_students']);
        $this->assertCount(30, $summary['enrollment_trend']);
        $this->assertSame(0, $summary['enrollment_trend']->sum('count'));
        $this->assertCount(0, $summary['recent_students']);
        $this->assertCount(0, $summary['overdue_student_tasks']);
    }

    public function test_metrics_and_queues_are_unavailable_without_student_permission(): void
    {
        Student::factory()->create();
        $summary = app(StudentsDashboardData::class)->summary(
            CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone')),
            canViewStudents: false,
            canCreateStudents: true,
            canViewTasks: true,
            canViewAllTasks: true,
        );

        $this->assertNull($summary['total_students']);
        $this->assertNull($summary['current_students']);
        $this->assertNull($summary['deleted_students']);
        $this->assertCount(0, $summary['enrollment_trend']);
        $this->assertCount(0, $summary['recent_students']);
        $this->assertCount(0, $summary['overdue_student_tasks']);
        $this->assertFalse($summary['can_view_students']);
    }

    public function test_quick_actions_require_their_own_resource_permissions(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => match ($ability) {
            'student.index' => true,
            'student.create' => false,
            'task.index' => false,
            'task.view_all' => false,
            default => null,
        });

        $data = (new StudentsDashboard)->getDashboardData();

        $this->assertNotNull($data['students_url']);
        $this->assertNull($data['create_student_url']);
        $this->assertNull($data['tasks_url']);
    }

    public function test_dashboard_denies_access_without_its_dedicated_page_permission(): void
    {
        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'students-dashboard.view' ? false : null);

        $this->assertFalse(StudentsDashboard::canAccess());
    }

    public function test_dashboard_keeps_its_dedicated_page_permission(): void
    {
        $page = new StudentsDashboard;

        $this->assertSame(
            ['students-dashboard.view' => __('Allows viewing the students dashboard')],
            $page->defineGates(),
        );
        $this->assertSame('filament.clusters.academic.pages.students-dashboard', $page->getView());

        $this->actingAs(User::factory()->create());
        Gate::before(fn ($user, string $ability): ?bool => $ability === 'students-dashboard.view' ? true : null);
        $this->assertTrue(StudentsDashboard::canAccess());
    }
}
