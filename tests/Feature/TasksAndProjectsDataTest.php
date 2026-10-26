<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Services\TasksAndProjectsData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksAndProjectsDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_task_statuses_and_applies_overdue_and_upcoming_date_boundaries(): void
    {
        $now = CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone'));
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->create(['start_date' => '2026-10-17', 'end_date' => '2026-10-17']);
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id, 'start_on' => '2026-10-17', 'end_on' => '2026-10-17']);

        Task::factory()->create(['action' => 'Overdue', 'assigned_to' => $user->id, 'created_by' => $user->id, 'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'pending', 'due_date' => '2026-10-16', 'estimated_effort_minutes' => 60]);
        Task::factory()->create(['action' => 'Due today', 'assigned_to' => $user->id, 'created_by' => $user->id, 'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'in_progress', 'due_date' => '2026-10-17', 'estimated_effort_minutes' => 30]);
        Task::factory()->create(['action' => 'Due at window end', 'assigned_to' => $user->id, 'created_by' => $user->id, 'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'pending', 'due_date' => '2026-10-24']);
        Task::factory()->create(['action' => 'Done overdue', 'assigned_to' => $user->id, 'created_by' => $user->id, 'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'done', 'due_date' => '2026-10-01']);
        Task::factory()->create(['action' => 'Deleted', 'assigned_to' => $user->id, 'created_by' => $user->id, 'project_id' => $project->id, 'project_phase_id' => $phase->id, 'status' => 'pending', 'due_date' => '2026-10-16'])->delete();

        $summary = app(TasksAndProjectsData::class)->summary($now);

        $this->assertSame(4, $summary['tasks_total']);
        $this->assertSame(3, $summary['tasks_open']);
        $this->assertSame(1, $summary['tasks_overdue']);
        $this->assertSame(3, (int) $summary['assignee_workload']->firstWhere('assigned_to', $user->id)->open_count);
        $this->assertSame(1, (int) $summary['assignee_workload']->firstWhere('assigned_to', $user->id)->overdue_count);
        $this->assertSame(90, (int) $summary['assignee_workload']->firstWhere('assigned_to', $user->id)->estimated_effort_minutes);
        $this->assertSame(1, (int) $summary['assignee_workload']->firstWhere('assigned_to', $user->id)->unestimated_count);
        $this->assertSame([
            ['status' => 'done', 'count' => 1],
            ['status' => 'in_progress', 'count' => 1],
            ['status' => 'pending', 'count' => 2],
        ], $summary['task_statuses']);
        $this->assertSame(['Overdue'], $summary['overdue_tasks']->pluck('action')->all());
        $this->assertSame(['Due today', 'Due at window end'], $summary['upcoming_tasks']->pluck('action')->all());
        $this->assertSame(1, $summary['projects_total']);
        $this->assertSame(1, $summary['projects_in_date_range']);
        $this->assertSame(1, $summary['phases_total']);
        $this->assertSame(1, $summary['phases_in_date_range']);

        $filtered = app(TasksAndProjectsData::class)->summary(
            $now,
            statusFilter: 'pending',
            ownerFilter: $user->id,
            projectFilter: $project->id,
            dueDateFilter: 'next_7_days',
        );

        $this->assertSame(2, $filtered['tasks_total']);
        $this->assertSame(1, $filtered['tasks_overdue']);
        $this->assertSame(['Due at window end'], $filtered['upcoming_tasks']->pluck('action')->all());
        $this->assertCount(0, $filtered['overdue_tasks']);
    }

    public function test_delivery_attention_counts_project_start_and_finish_date_variance(): void
    {
        $now = CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone'));
        $user = User::factory()->create();
        $this->actingAs($user);
        Project::factory()->create([
            'planned_start_date' => '2026-10-01', 'start_date' => '2026-10-03',
            'planned_end_date' => '2026-10-10', 'end_date' => '2026-10-12',
        ]);
        Project::factory()->create([
            'planned_start_date' => '2026-10-01', 'start_date' => '2026-10-01',
            'planned_end_date' => '2026-10-20', 'end_date' => '2026-10-20',
        ]);

        $summary = app(TasksAndProjectsData::class)->summary($now);

        $this->assertSame(1, $summary['projects_started_after_plan']);
        $this->assertSame(1, $summary['projects_finished_after_plan']);
    }

    public function test_task_summary_obeys_the_task_resources_assigned_or_created_scope(): void
    {
        $now = CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);

        Task::factory()->create(['action' => 'Assigned to me', 'assigned_to' => $user->id, 'created_by' => $otherUser->id, 'due_date' => '2026-10-18', 'project_id' => $project->id, 'project_phase_id' => $phase->id]);
        Task::factory()->create(['action' => 'Created by me', 'assigned_to' => $otherUser->id, 'created_by' => $user->id, 'due_date' => '2026-10-19', 'project_id' => $project->id, 'project_phase_id' => $phase->id]);
        $this->actingAs($otherUser);
        Task::factory()->create(['action' => 'Not visible', 'assigned_to' => $otherUser->id, 'created_by' => $otherUser->id, 'project_id' => $project->id, 'project_phase_id' => $phase->id]);
        $this->actingAs($user);

        $summary = app(TasksAndProjectsData::class)->summary($now, canViewTasks: true, canViewAllTasks: false);

        $this->assertSame(2, $summary['tasks_total']);
        $this->assertSame(['Assigned to me', 'Created by me'], $summary['upcoming_tasks']->pluck('action')->all());
    }

    public function test_task_metrics_are_hidden_when_user_cannot_view_the_task_resource(): void
    {
        $summary = app(TasksAndProjectsData::class)->summary(
            CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone')),
            canViewTasks: false,
            canViewProjects: false,
            canViewPhases: false,
        );

        $this->assertNull($summary['tasks_total']);
        $this->assertNull($summary['tasks_open']);
        $this->assertSame(0, $summary['tasks_overdue']);
        $this->assertCount(0, $summary['assignee_workload']);
        $this->assertNull($summary['projects_started_after_plan']);
        $this->assertNull($summary['projects_finished_after_plan']);
        $this->assertSame([], $summary['task_statuses']);
        $this->assertCount(0, $summary['upcoming_tasks']);
        $this->assertNull($summary['projects_total']);
        $this->assertNull($summary['phases_total']);
    }

    public function test_task_project_and_phase_relationships_match_the_foreign_key_columns(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
        ]);

        $this->assertTrue($task->project->is($project));
        $this->assertTrue($task->projectPhase->is($phase));
        $this->assertTrue($phase->project->is($project));
    }
}
