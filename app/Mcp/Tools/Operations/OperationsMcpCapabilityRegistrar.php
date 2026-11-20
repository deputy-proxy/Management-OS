<?php

namespace App\Mcp\Tools\Operations;

use App\Mcp\Authorization\AuthorizedMcpCapabilityRegistrar;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\QuarterlyObjective;
use App\Models\Task;
use App\Models\User;
use App\Services\TasksAndProjectsData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Mcp\Server\Builder as McpBuilder;

final class OperationsMcpCapabilityRegistrar extends AuthorizedMcpCapabilityRegistrar
{
    private const TZ = 'Europe/Bucharest';

    public function register(McpBuilder $builder): void
    {
        $tools = [
            'get_projects' => fn (User $user, array $a): array => $this->projects($user, $a),
            'get_project_details' => fn (User $user, array $a): array => $this->projectDetails($user, $a),
            'get_tasks' => fn (User $user, array $a): array => $this->tasks($user, $a),
            'get_task_details' => fn (User $user, array $a): array => $this->taskDetails($user, $a),
            'get_overdue_tasks' => fn (User $user, array $a): array => $this->overdueTasks($user, $a),
            'get_operational_bottlenecks' => fn (User $user, array $a): array => $this->operationalBottlenecks($user),
            'get_quarterly_objectives' => fn (User $user, array $a): array => $this->quarterlyObjectives($user, $a),
            'get_meetings' => fn (User $user, array $a): array => $this->meetings($user, $a),
        ];
        $descriptions = [
            'get_projects' => 'Lists visible projects with bounded filters and pagination. Descriptions and budget amounts are excluded.',
            'get_project_details' => 'Returns a limited project profile and project phases only when the principal has project-phase visibility.',
            'get_tasks' => 'Lists tasks visible to the principal, applying assigned/created ownership unless task.view_all is granted.',
            'get_task_details' => 'Returns a limited visible task profile without free-text description, creator identity or dependency records.',
            'get_overdue_tasks' => 'Lists visible tasks whose non-null due date is earlier than the requested as-of date and whose status is not done.',
            'get_operational_bottlenecks' => 'Returns workload and delivery-risk aggregates from the existing TasksAndProjectsData service, scoped to visible tasks.',
            'get_quarterly_objectives' => 'Lists visible quarterly objectives with employee name, objective, quarter and status. Free-text descriptions are excluded.',
            'get_meetings' => 'Lists meetings in a bounded start-date range. Descriptions and participant identities are excluded.',
        ];

        foreach ($tools as $name => $handler) {
            $this->addAuthorizedTool($builder, $name, $handler, $descriptions[$name], $this->schema($name));
        }
    }

    private function projects(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'status' => ['sometimes', 'string', 'max:50'],
            'project_manager_id' => ['sometimes', 'integer', 'min:1'],
            'planned_start_from' => ['sometimes', 'date_format:Y-m-d'],
            'planned_start_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:planned_start_from'],
            'sort_by' => ['sometimes', Rule::in(['title', 'status', 'planned_start_date', 'start_date', 'end_date'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Builder<Project> $query */
            $query = $this->scopeQuery($user, 'get_projects', Project::query())
                ->with('ProjectManager:id,name');
            if (isset($v['status'])) {
                $query->where('status', $v['status']);
            }
            if (isset($v['project_manager_id'])) {
                $query->where('project_manager', $v['project_manager_id']);
            }
            if (isset($v['planned_start_from'])) {
                $query->whereDate('planned_start_date', '>=', $v['planned_start_from']);
            }
            if (isset($v['planned_start_to'])) {
                $query->whereDate('planned_start_date', '<=', $v['planned_start_to']);
            }
            $query->select([
                'id', 'title', 'project_manager', 'status',
                'planned_start_date', 'planned_end_date', 'start_date', 'end_date',
            ])->orderBy($v['sort_by'] ?? 'planned_start_date', $v['sort_direction'] ?? 'asc')
                ->orderBy('id');

            return [
                'availability' => 'available',
                ...$this->pageQuery($query, $v, 'projects', fn (Project $project): array => $this->projectDto($project)),
            ];
        });
    }

    private function projectDetails(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, ['project_id' => ['required', 'integer', 'min:1']])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Builder<Project> $query */
            /** @var Builder<Project> $query */
            $query = $this->scopeQuery($user, 'get_project_details', Project::query())
                ->with('ProjectManager:id,name')
                ->select(['id', 'title', 'project_manager', 'status', 'planned_start_date', 'planned_end_date', 'start_date', 'end_date']);
            /** @var Project|null $project */
            $project = $query->whereKey($v['project_id'])->first();
            if (! $project) {
                return ['availability' => 'unavailable_project_not_found'];
            }
            $this->authorizeRecord($user, 'get_project_details', $project);

            $phasesAvailable = $this->authorization->hasDomainPermission($user, 'project_phase.index');
            $phases = [];
            $phaseCount = null;
            $phasesTruncated = false;
            if ($phasesAvailable) {
                /** @var Builder<ProjectPhase> $phaseQuery */
                $phaseQuery = $this->scopeQuery($user, 'get_project_details', ProjectPhase::query())
                    ->where('project_id', $project->getKey())
                    ->orderBy('order')->orderBy('id')
                    ->select(['id', 'title', 'start_on', 'end_on', 'order']);
                $phaseCount = (clone $phaseQuery)->count();
                $phases = $phaseQuery->limit(100)->get()
                    ->map(fn (ProjectPhase $phase): array => [
                        'title' => (string) $phase->getAttribute('title'),
                        'start_on' => $this->dateValue($phase->getAttribute('start_on')),
                        'end_on' => $this->dateValue($phase->getAttribute('end_on')),
                        'order' => (int) $phase->getAttribute('order'),
                    ])->all();
                $phasesTruncated = $phaseCount > count($phases);
            }

            return [
                'availability' => 'available',
                'project' => $this->projectDto($project),
                'phases_available' => $phasesAvailable,
                'phase_count' => $phaseCount,
                'phases_truncated' => $phasesTruncated,
                'phases' => $phases,
            ];
        });
    }

    private function tasks(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'done'])],
            'assigned_to' => ['sometimes', 'integer', 'min:1'],
            'project_id' => ['sometimes', 'integer', 'min:1'],
            'due_date_from' => ['sometimes', 'date_format:Y-m-d'],
            'due_date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:due_date_from'],
            'sort_by' => ['sometimes', Rule::in(['action', 'status', 'due_date', 'created_at'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Builder<Task> $query */
            $query = $this->scopeQuery($user, 'get_tasks', Task::query())
                ->with(['assignedTo:id,name', 'project:id,title', 'projectPhase:id,title']);
            if (isset($v['status'])) {
                $query->where('status', $v['status']);
            }
            if (isset($v['assigned_to'])) {
                $query->where('assigned_to', $v['assigned_to']);
            }
            if (isset($v['project_id'])) {
                $query->where('project_id', $v['project_id']);
            }
            if (isset($v['due_date_from'])) {
                $query->whereDate('due_date', '>=', $v['due_date_from']);
            }
            if (isset($v['due_date_to'])) {
                $query->whereDate('due_date', '<=', $v['due_date_to']);
            }
            $query->select([
                'id', 'action', 'status', 'due_date', 'estimated_effort_minutes',
                'assigned_to', 'project_id', 'project_phase_id', 'created_at',
            ])->orderBy($v['sort_by'] ?? 'due_date', $v['sort_direction'] ?? 'asc')
                ->orderBy('id');

            return [
                'availability' => 'available',
                'as_of' => CarbonImmutable::now(self::TZ)->toIso8601String(),
                'timezone' => self::TZ,
                ...$this->pageQuery($query, $v, 'tasks', fn (Task $task): array => $this->taskDto($task)),
            ];
        });
    }

    private function taskDetails(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, ['task_id' => ['required', 'integer', 'min:1']])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Builder<Task> $query */
            $query = $this->scopeQuery($user, 'get_task_details', Task::query())
                ->with(['assignedTo:id,name', 'project:id,title', 'projectPhase:id,title'])
                ->select(['id', 'action', 'status', 'due_date', 'estimated_effort_minutes', 'assigned_to', 'project_id', 'project_phase_id', 'created_at']);
            /** @var Task|null $task */
            $task = $query->whereKey($v['task_id'])->first();
            if (! $task) {
                return ['availability' => 'unavailable_task_not_found'];
            }
            $this->authorizeRecord($user, 'get_task_details', $task);

            return ['availability' => 'available', 'task' => $this->taskDto($task)];
        });
    }

    private function overdueTasks(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'as_of' => ['sometimes', 'date_format:Y-m-d'],
            'assigned_to' => ['sometimes', 'integer', 'min:1'],
            'project_id' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            $asOf = CarbonImmutable::parse($v['as_of'] ?? CarbonImmutable::now(self::TZ)->toDateString(), self::TZ)->startOfDay();
            /** @var Builder<Task> $query */
            $query = $this->scopeQuery($user, 'get_overdue_tasks', Task::query())
                ->with(['assignedTo:id,name', 'project:id,title', 'projectPhase:id,title'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $asOf->toDateString())
                ->where('status', '!=', 'done');
            if (isset($v['assigned_to'])) {
                $query->where('assigned_to', $v['assigned_to']);
            }
            if (isset($v['project_id'])) {
                $query->where('project_id', $v['project_id']);
            }
            $query->select([
                'id', 'action', 'status', 'due_date', 'estimated_effort_minutes',
                'assigned_to', 'project_id', 'project_phase_id', 'created_at',
            ])->orderBy('due_date')->orderBy('id');

            return [
                'availability' => 'available',
                'as_of' => $asOf->toIso8601String(),
                'timezone' => self::TZ,
                'basis' => 'Due date is strictly earlier than as_of and status is not done. Null due dates are excluded; unrecognized status values are returned unchanged.',
                ...$this->pageQuery($query, $v, 'tasks', function (Task $task) use ($asOf): array {
                    $dueDate = CarbonImmutable::parse($task->getAttribute('due_date'), self::TZ);

                    return [
                        ...$this->taskDto($task),
                        'overdue_days' => (int) $dueDate->diffInDays($asOf),
                    ];
                }),
            ];
        });
    }

    private function operationalBottlenecks(User $user): array
    {
        return $this->withPrincipalContext($user, function () use ($user): array {
            $now = CarbonImmutable::now(self::TZ);
            /** @var Builder<Task> $visibleTaskQuery */
            $visibleTaskQuery = $this->scopeQuery($user, 'get_operational_bottlenecks', Task::query());
            $canViewProjects = $this->authorization->hasDomainPermission($user, 'project.index');
            $canViewPhases = $this->authorization->hasDomainPermission($user, 'project_phase.index');
            $summary = app(TasksAndProjectsData::class)->summary(
                now: $now,
                canViewTasks: true,
                canViewAllTasks: true,
                canViewProjects: $canViewProjects,
                canViewPhases: $canViewPhases,
                authorizedTaskQuery: $visibleTaskQuery,
            );

            $workload = $summary['assignee_workload']->map(function ($row): array {
                $assignee = $row->getRelation('assignedTo');

                return [
                    'assignee_name' => $assignee instanceof User ? (string) $assignee->name : null,
                    'open_count' => (int) $row->getAttribute('open_count'),
                    'overdue_count' => (int) $row->getAttribute('overdue_count'),
                    'estimated_effort_minutes' => (int) $row->getAttribute('estimated_effort_minutes'),
                    'unestimated_count' => (int) $row->getAttribute('unestimated_count'),
                ];
            })->all();

            return [
                'availability' => 'available',
                'as_of' => $now->toIso8601String(),
                'week_end' => $now->addDays(7)->toDateString(),
                'timezone' => self::TZ,
                'task_total' => $summary['tasks_total'],
                'task_open' => $summary['tasks_open'],
                'task_overdue' => $summary['tasks_overdue'],
                'task_statuses' => $summary['task_statuses'],
                'assignee_workload' => $workload,
                'project_schedule' => $canViewProjects ? [
                    'projects_total' => $summary['projects_total'],
                    'projects_in_date_range' => $summary['projects_in_date_range'],
                    'projects_started_after_plan' => $summary['projects_started_after_plan'],
                    'projects_finished_after_plan' => $summary['projects_finished_after_plan'],
                    'phases_total' => $canViewPhases ? $summary['phases_total'] : null,
                    'phases_in_date_range' => $canViewPhases ? $summary['phases_in_date_range'] : null,
                ] : null,
                'status_basis' => 'Open-task counts follow the existing service rule status != done. Status values are returned as stored; unknown values are not normalized.',
                'due_date_basis' => 'Overdue means a non-null due date strictly earlier than the Europe/Bucharest current date. Missing due dates are not inferred.',
            ];
        });
    }

    private function quarterlyObjectives(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'quarter' => ['sometimes', 'string', 'max:30'],
            'status' => ['sometimes', 'string', 'max:50'],
            'sort_by' => ['sometimes', Rule::in(['quarter', 'status', 'created_at'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Builder<QuarterlyObjective> $query */
            $query = $this->scopeQuery($user, 'get_quarterly_objectives', QuarterlyObjective::query())
                ->with('employee:id,full_name');
            if (isset($v['quarter'])) {
                $query->where('quarter', $v['quarter']);
            }
            if (isset($v['status'])) {
                $query->where('status', $v['status']);
            }
            $query->select(['id', 'employee_id', 'objectives', 'quarter', 'status', 'created_at'])
                ->orderBy($v['sort_by'] ?? 'quarter', $v['sort_direction'] ?? 'asc')
                ->orderBy('id');

            return [
                'availability' => 'available',
                ...$this->pageQuery($query, $v, 'objectives', function (QuarterlyObjective $objective): array {
                    $employee = $objective->getRelation('employee');

                    return [
                        'id' => (int) $objective->getKey(),
                        'employee_name' => $employee instanceof Employee ? (string) $employee->full_name : null,
                        'objective' => (string) $objective->getAttribute('objectives'),
                        'quarter' => (string) $objective->getAttribute('quarter'),
                        'status' => (string) $objective->getAttribute('status'),
                        'created_at' => $this->dateTimeValue($objective->getAttribute('created_at')),
                    ];
                }),
            ];
        });
    }

    private function meetings(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'query' => ['sometimes', 'string', 'min:2', 'max:100'],
            'type' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'max:50'],
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'sort_by' => ['sometimes', Rule::in(['starts_at', 'subject', 'status'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Builder<Meeting> $query */
            $query = $this->scopeQuery($user, 'get_meetings', Meeting::query())
                ->with('organizer:id,full_name');
            if (isset($v['query'])) {
                $query->whereRaw("subject LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($v['query']).'%']);
            }
            if (isset($v['type'])) {
                $query->where('type', $v['type']);
            }
            if (isset($v['status'])) {
                $query->where('status', $v['status']);
            }
            if (isset($v['from_date'])) {
                $query->where('starts_at', '>=', CarbonImmutable::parse($v['from_date'], self::TZ)->startOfDay());
            }
            if (isset($v['to_date'])) {
                $query->where('starts_at', '<=', CarbonImmutable::parse($v['to_date'], self::TZ)->endOfDay());
            }
            $query->select(['id', 'subject', 'type', 'status', 'starts_at', 'ends_at', 'location', 'organizer_id', 'participants'])
                ->orderBy($v['sort_by'] ?? 'starts_at', $v['sort_direction'] ?? 'asc')
                ->orderBy('id');

            return [
                'availability' => 'available',
                'timezone' => self::TZ,
                ...$this->pageQuery($query, $v, 'meetings', function (Meeting $meeting): array {
                    $organizer = $meeting->getRelation('organizer');
                    $participants = $meeting->getAttribute('participants');

                    return [
                        'id' => (int) $meeting->getKey(),
                        'subject' => (string) $meeting->getAttribute('subject'),
                        'type' => (string) $meeting->getAttribute('type'),
                        'status' => (string) $meeting->getAttribute('status'),
                        'starts_at' => $this->dateTimeValue($meeting->getAttribute('starts_at')),
                        'ends_at' => $this->dateTimeValue($meeting->getAttribute('ends_at')),
                        'location' => (string) $meeting->getAttribute('location'),
                        'organizer_name' => $organizer instanceof Employee ? (string) $organizer->full_name : null,
                        'participants_count' => is_array($participants) ? count($participants) : null,
                    ];
                }),
            ];
        });
    }

    private function projectDto(Project $project): array
    {
        $manager = $project->getRelation('ProjectManager');

        return [
            'id' => (int) $project->getKey(),
            'title' => (string) $project->getAttribute('title'),
            'project_manager_name' => $manager instanceof User ? (string) $manager->name : null,
            'status' => (string) $project->getAttribute('status'),
            'planned_start_date' => $this->dateValue($project->getAttribute('planned_start_date')),
            'planned_end_date' => $this->dateValue($project->getAttribute('planned_end_date')),
            'start_date' => $this->dateValue($project->getAttribute('start_date')),
            'end_date' => $this->dateValue($project->getAttribute('end_date')),
        ];
    }

    private function taskDto(Task $task): array
    {
        $assignee = $task->getRelation('assignedTo');
        $project = $task->getRelation('project');
        $phase = $task->getRelation('projectPhase');

        return [
            'id' => (int) $task->getKey(),
            'action' => (string) $task->getAttribute('action'),
            'status' => (string) $task->getAttribute('status'),
            'due_date' => $this->dateValue($task->getAttribute('due_date')),
            'estimated_effort_minutes' => $task->getAttribute('estimated_effort_minutes') === null
                ? null
                : (int) $task->getAttribute('estimated_effort_minutes'),
            'assignee_name' => $assignee instanceof User ? (string) $assignee->name : null,
            'project_title' => $project instanceof Project ? (string) $project->title : null,
            'project_phase_title' => $phase instanceof ProjectPhase ? (string) $phase->title : null,
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function pageQuery(Builder $query, array $v, string $key, callable $map): array
    {
        $limit = (int) ($v['limit'] ?? 50);
        $offset = (int) ($v['offset'] ?? 0);
        $total = (clone $query)->count();
        $items = $query->limit($limit)->offset($offset)->get()->map($map)->values()->all();

        return [
            'total_count' => $total,
            'returned_count' => count($items),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $total > $offset + $limit,
            $key => $items,
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::TZ)->toDateString();
        }

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, self::TZ)->toDateString() : null;
    }

    private function dateTimeValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::TZ)->toIso8601String();
        }

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, self::TZ)->toIso8601String() : null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private function schema(string $name): array
    {
        $properties = match ($name) {
            'get_projects' => [
                'status' => ['type' => 'string', 'maxLength' => 50],
                'project_manager_id' => ['type' => 'integer', 'minimum' => 1],
                'planned_start_from' => ['type' => 'string', 'format' => 'date'],
                'planned_start_to' => ['type' => 'string', 'format' => 'date'],
                'sort_by' => ['type' => 'string', 'enum' => ['title', 'status', 'planned_start_date', 'start_date', 'end_date']],
                'sort_direction' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            'get_project_details' => ['project_id' => ['type' => 'integer', 'minimum' => 1]],
            'get_tasks' => [
                'status' => ['type' => 'string', 'enum' => ['pending', 'in_progress', 'done']],
                'assigned_to' => ['type' => 'integer', 'minimum' => 1],
                'project_id' => ['type' => 'integer', 'minimum' => 1],
                'due_date_from' => ['type' => 'string', 'format' => 'date'],
                'due_date_to' => ['type' => 'string', 'format' => 'date'],
                'sort_by' => ['type' => 'string', 'enum' => ['action', 'status', 'due_date', 'created_at']],
                'sort_direction' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            'get_task_details' => ['task_id' => ['type' => 'integer', 'minimum' => 1]],
            'get_overdue_tasks' => [
                'as_of' => ['type' => 'string', 'format' => 'date'],
                'assigned_to' => ['type' => 'integer', 'minimum' => 1],
                'project_id' => ['type' => 'integer', 'minimum' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            'get_quarterly_objectives' => [
                'quarter' => ['type' => 'string', 'maxLength' => 30],
                'status' => ['type' => 'string', 'maxLength' => 50],
                'sort_by' => ['type' => 'string', 'enum' => ['quarter', 'status', 'created_at']],
                'sort_direction' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            'get_meetings' => [
                'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                'type' => ['type' => 'string', 'maxLength' => 50],
                'status' => ['type' => 'string', 'maxLength' => 50],
                'from_date' => ['type' => 'string', 'format' => 'date'],
                'to_date' => ['type' => 'string', 'format' => 'date'],
                'sort_by' => ['type' => 'string', 'enum' => ['starts_at', 'subject', 'status']],
                'sort_direction' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            default => [],
        };
        $required = match ($name) {
            'get_project_details' => ['project_id'],
            'get_task_details' => ['task_id'],
            default => [],
        };

        return [
            'type' => 'object',
            'properties' => (object) $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
