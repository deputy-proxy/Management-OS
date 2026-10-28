<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\TaskEscalation;
use App\Models\User;
use App\Services\TaskEscalationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_escalations_are_deduplicated_and_only_include_tasks_past_threshold(): void
    {
        [$owner, $overdue, $dueToday, $recentlyOverdue, $completed] = $this->makeTasks();
        $overdue->update(['due_date' => '2026-10-25', 'status' => 'pending']);
        $dueToday->update(['due_date' => '2026-10-27', 'status' => 'pending']);
        $recentlyOverdue->update(['due_date' => '2026-10-26', 'status' => 'in_progress']);
        $completed->update(['due_date' => '2026-10-20', 'status' => 'done']);

        $service = app(TaskEscalationService::class);
        $asOf = CarbonImmutable::parse('2026-10-27 08:00:00', 'Europe/Bucharest');

        $this->assertSame(1, $service->escalateOverdueTasks($asOf, 2));
        $this->assertSame(0, $service->escalateOverdueTasks($asOf, 2));

        $escalation = TaskEscalation::query()->sole();
        $this->assertSame($overdue->id, $escalation->task_id);
        $this->assertSame($owner->id, $escalation->owner_id);
        $this->assertSame('open', $escalation->status);
        $this->assertSame('2026-10-25', $escalation->due_date->toDateString());
    }

    public function test_escalation_owner_can_acknowledge_and_acknowledgement_is_idempotent(): void
    {
        [$owner, $task] = $this->makeTasks(2);
        $task->update(['due_date' => '2026-10-25', 'status' => 'pending']);
        app(TaskEscalationService::class)->escalateOverdueTasks(CarbonImmutable::parse('2026-10-27', 'Europe/Bucharest'), 1);
        $escalation = TaskEscalation::query()->sole();
        $this->actingAs($owner);

        $acknowledged = app(TaskEscalationService::class)->acknowledge($escalation);
        $again = app(TaskEscalationService::class)->acknowledge($acknowledged);

        $this->assertSame(TaskEscalation::STATUS_ACKNOWLEDGED, $again->status);
        $this->assertSame($owner->id, $again->acknowledged_by);
        $this->assertNotNull($again->acknowledged_at);
    }

    public function test_unrelated_user_cannot_acknowledge_task_escalation(): void
    {
        [, $task] = $this->makeTasks(2);
        $task->update(['due_date' => '2026-10-25', 'status' => 'pending']);
        app(TaskEscalationService::class)->escalateOverdueTasks(CarbonImmutable::parse('2026-10-27', 'Europe/Bucharest'), 1);
        $escalation = TaskEscalation::query()->sole();
        $this->actingAs(User::factory()->create());

        $this->expectException(AuthorizationException::class);
        app(TaskEscalationService::class)->acknowledge($escalation);
    }

    /** @return array<int, User|Task> */
    private function makeTasks(int $count = 4): array
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);
        $tasks = Task::factory()->count($count)->create([
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
            'status' => 'pending',
        ])->all();

        return [$owner, ...$tasks];
    }
}
