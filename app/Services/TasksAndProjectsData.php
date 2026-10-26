<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TasksAndProjectsData
{
    /**
     * @return array<string, mixed>
     */
    public function summary(
        ?CarbonImmutable $now = null,
        bool $canViewTasks = true,
        bool $canViewAllTasks = true,
        bool $canViewProjects = true,
        bool $canViewPhases = true,
        ?string $statusFilter = null,
        ?int $ownerFilter = null,
        ?int $projectFilter = null,
        string $dueDateFilter = 'all',
    ): array {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $today = $now->toDateString();
        $weekEnd = $now->addDays(7)->toDateString();

        $taskQuery = Task::query()->with(['project:id,title', 'projectPhase:id,title', 'assignedTo:id,name']);
        if (! $canViewAllTasks && auth()->check()) {
            $userId = auth()->id();
            $taskQuery->where(fn (Builder $query) => $query
                ->where('assigned_to', $userId)
                ->orWhere('created_by', $userId));
        } elseif (! $canViewAllTasks && ! auth()->check()) {
            $taskQuery->whereRaw('1 = 0');
        }

        $tasks = $canViewTasks ? (clone $taskQuery) : Task::query()->whereRaw('1 = 0');
        if ($canViewTasks && $statusFilter !== null && $statusFilter !== '') {
            $tasks->where('status', $statusFilter);
        }
        if ($canViewTasks && $ownerFilter !== null) {
            $tasks->where('assigned_to', $ownerFilter);
        }
        if ($canViewTasks && $projectFilter !== null) {
            $tasks->where('project_id', $projectFilter);
        }

        $visibleTasks = $canViewTasks ? (clone $taskQuery) : Task::query()->whereRaw('1 = 0');
        $ownerIds = $canViewTasks ? (clone $visibleTasks)->select('assigned_to')->distinct()->pluck('assigned_to') : collect();
        $projectIds = $canViewTasks ? (clone $visibleTasks)->select('project_id')->distinct()->pluck('project_id') : collect();
        $filterOptions = [
            'owners' => User::query()->whereIn('id', $ownerIds)->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => Project::query()->whereIn('id', $projectIds)->orderBy('title')->pluck('title', 'id')->all(),
        ];
        $statusCounts = $canViewTasks
            ? (clone $visibleTasks)->select('status')->selectRaw('COUNT(*) as record_count')->groupBy('status')->orderBy('status')->get()
                ->map(fn (object $row): array => ['status' => (string) $row->status, 'count' => (int) $row->record_count])->all()
            : [];

        $openTasks = (clone $visibleTasks)->where('status', '!=', 'done');
        $overdue = $canViewTasks
            ? (clone $openTasks)->whereDate('due_date', '<', $today)->count()
            : 0;

        $assigneeWorkload = $canViewTasks
            ? (clone $openTasks)->select('assigned_to')
                ->selectRaw('COUNT(*) as open_count')
                ->selectRaw('SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as overdue_count', [$today])
                ->selectRaw('COALESCE(SUM(estimated_effort_minutes), 0) as estimated_effort_minutes')
                ->selectRaw('SUM(CASE WHEN estimated_effort_minutes IS NULL THEN 1 ELSE 0 END) as unestimated_count')
                ->with('assignedTo:id,name')
                ->groupBy('assigned_to')
                ->orderByDesc('open_count')
                ->orderBy('assigned_to')
                ->limit(10)
                ->get()
            : collect();

        $upcoming = $canViewTasks && $dueDateFilter !== 'overdue'
            ? (clone $tasks)->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', $weekEnd)->where('status', '!=', 'done')
                ->orderBy('due_date')->orderBy('id')->limit(8)->get()
            : collect();

        $overdueTasks = $canViewTasks && $dueDateFilter !== 'next_7_days'
            ? (clone $tasks)->whereDate('due_date', '<', $today)->where('status', '!=', 'done')
                ->orderBy('due_date')->orderBy('id')->limit(8)->get()
            : collect();

        return [
            'as_of' => $now->format('F j, Y'),
            'week_end' => $now->addDays(7)->format('M j, Y'),
            'tasks_total' => $canViewTasks ? (clone $tasks)->count() : null,
            'tasks_open' => $canViewTasks ? (clone $tasks)->where('status', '!=', 'done')->count() : null,
            'tasks_overdue' => $overdue,
            'assignee_workload' => $assigneeWorkload,
            'projects_started_after_plan' => $canViewProjects
                ? Project::query()->whereColumn('start_date', '>', 'planned_start_date')->count()
                : null,
            'projects_finished_after_plan' => $canViewProjects
                ? Project::query()->whereColumn('end_date', '>', 'planned_end_date')->count()
                : null,
            'task_statuses' => $statusCounts,
            'task_filter_options' => $filterOptions,
            'upcoming_tasks' => $upcoming instanceof Collection ? $upcoming : collect($upcoming),
            'overdue_tasks' => $overdueTasks instanceof Collection ? $overdueTasks : collect($overdueTasks),
            'projects_total' => $canViewProjects ? Project::query()->count() : null,
            'projects_in_date_range' => $canViewProjects
                ? Project::query()->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count()
                : null,
            'phases_total' => $canViewPhases ? ProjectPhase::query()->count() : null,
            'phases_in_date_range' => $canViewPhases
                ? ProjectPhase::query()->whereDate('start_on', '<=', $today)->whereDate('end_on', '>=', $today)->count()
                : null,
        ];
    }
}
