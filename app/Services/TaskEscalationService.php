<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskEscalation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class TaskEscalationService
{
    /**
     * Record one durable escalation per task and due-date cycle.
     * Re-running the scheduled command is safe and never duplicates a cycle.
     *
     * @return int Number of newly created escalation records
     */
    public function escalateOverdueTasks(?CarbonImmutable $asOf = null, ?int $minimumOverdueDays = null): int
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $asOf = ($asOf ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $minimumOverdueDays ??= max(1, (int) config('tasks.escalation_overdue_days', 1));
        if ($minimumOverdueDays < 1) {
            throw new LogicException('Task escalation threshold must be at least one overdue day.');
        }
        $cutoff = $asOf->startOfDay()->subDays($minimumOverdueDays)->toDateString();
        $created = 0;

        Task::query()
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use ($asOf, &$created): void {
                foreach ($tasks as $task) {
                    $escalation = TaskEscalation::query()->firstOrCreate(
                        [
                            'task_id' => $task->id,
                            'due_date' => $task->due_date->toDateString(),
                            'level' => 'overdue',
                        ],
                        [
                            'status' => TaskEscalation::STATUS_OPEN,
                            'owner_id' => $task->assigned_to ?: $task->created_by,
                            'reason' => sprintf('Task remains %s past its due date.', $task->status ?: 'incomplete'),
                            'escalated_at' => $asOf,
                        ],
                    );

                    if ($escalation->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    public function acknowledge(TaskEscalation $escalation): TaskEscalation
    {
        $user = auth()->user();
        if ($user === null) {
            throw new AuthorizationException('Sign in to acknowledge a task escalation.');
        }

        $task = $escalation->task;
        $canManageAll = hexa()->can('task.update') || hexa()->can('task.view_all');
        $isParticipant = (int) $escalation->owner_id === (int) $user->id
            || (int) $task->assigned_to === (int) $user->id
            || (int) $task->created_by === (int) $user->id;
        if (! $canManageAll && ! $isParticipant) {
            throw new AuthorizationException('Only the escalation owner, task participants, or task managers may acknowledge this escalation.');
        }

        if ($escalation->status === TaskEscalation::STATUS_ACKNOWLEDGED) {
            return $escalation;
        }

        $escalation->status = TaskEscalation::STATUS_ACKNOWLEDGED;
        $escalation->acknowledged_at = now();
        $escalation->acknowledged_by = $user->id;
        $escalation->save();

        return $escalation->refresh();
    }
}
