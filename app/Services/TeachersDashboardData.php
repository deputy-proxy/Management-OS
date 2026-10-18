<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Group;
use App\Models\Session;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class TeachersDashboardData
{
    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function summary(
        array $permissions,
        ?CarbonImmutable $now = null,
        string $period = '30',
    ): array {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $periodDays = in_array($period, ['7', '30', '90'], true) ? (int) $period : 30;
        $today = $now->startOfDay();
        $rangeStart = $today->subDays($periodDays - 1);
        $rangeEnd = $today->addDay();

        $canViewEmployees = $permissions['employee.index'] ?? false;
        $canCreateEmployees = $permissions['employee.create'] ?? false;
        $canViewGroups = $permissions['group.index'] ?? false;
        $sessionTypes = $this->visibleSessionTypes($permissions);
        $canViewSessions = $sessionTypes !== [];

        $teacherTotal = $canViewEmployees ? $this->teachersQuery(withTrashed: true)->count() : null;
        $teacherCurrent = $canViewEmployees ? $this->teachersQuery()->count() : null;
        $teacherDeleted = $canViewEmployees ? $this->teachersQuery(onlyTrashed: true)->count() : null;

        $groupTotal = $canViewGroups ? Group::query()->count() : null;
        $groupsWithoutTeacher = $canViewGroups && $canViewEmployees
            ? Group::query()->whereDoesntHave('teacher', fn (Builder $query) => $this->constrainTeacherRole($query))->count()
            : null;

        $scheduledSessions = null;
        $assignedSessions = null;
        $unassignedSessions = null;
        $sessionsWithoutTeacherRole = null;
        $sessionTrend = collect();
        $maxDailySessions = 1;
        $sessionCounts = collect();

        if ($canViewSessions) {
            $scheduledSessionsQuery = $this->scopedSessions($sessionTypes)
                ->where('status', 'scheduled')
                ->where('starts_at', '>=', $rangeStart)
                ->where('starts_at', '<', $rangeEnd);

            $scheduledSessions = (clone $scheduledSessionsQuery)->count();
            $assignedSessions = (clone $scheduledSessionsQuery)->whereNotNull('teacher_id')->count();
            $unassignedSessions = (clone $scheduledSessionsQuery)->whereNull('teacher_id')->count();

            $dailyCounts = (clone $scheduledSessionsQuery)
                ->selectRaw('DATE(starts_at) as session_date, COUNT(*) as session_count')
                ->groupByRaw('DATE(starts_at)')
                ->orderBy('session_date')
                ->pluck('session_count', 'session_date');

            $sessionTrend = collect(range(0, $periodDays - 1))
                ->map(function (int $offset) use ($rangeStart, $dailyCounts): array {
                    $date = $rangeStart->addDays($offset)->toDateString();

                    return [
                        'date' => $date,
                        'label' => CarbonImmutable::parse($date, config('app.timezone'))->format('M j'),
                        'count' => (int) ($dailyCounts[$date] ?? 0),
                    ];
                });
            $maxDailySessions = max(1, (int) $sessionTrend->max('count'));

            if ($canViewEmployees) {
                $sessionsWithoutTeacherRole = (clone $scheduledSessionsQuery)
                    ->whereNotNull('teacher_id')
                    ->whereDoesntHave('teacher', fn (Builder $query) => $this->constrainTeacherRole($query))
                    ->count();
            }

            if ($canViewEmployees) {
                $sessionCounts = (clone $scheduledSessionsQuery)
                    ->whereNotNull('teacher_id')
                    ->selectRaw('teacher_id, COUNT(*) as session_count')
                    ->groupBy('teacher_id')
                    ->pluck('session_count', 'teacher_id');
            }
        }

        $groupCounts = $canViewGroups
            ? Group::query()->whereNotNull('teacher_id')
                ->selectRaw('teacher_id, COUNT(*) as group_count')
                ->groupBy('teacher_id')->pluck('group_count', 'teacher_id')
            : collect();

        $hasAssignmentSources = $canViewGroups || $canViewSessions;
        $teacherWorkload = collect();
        $teachersWithAssignments = null;
        $teachersWithoutAssignments = null;

        if ($canViewEmployees && $hasAssignmentSources) {
            $teacherWorkload = $this->teachersQuery()->orderBy('full_name')->get(['id', 'full_name'])
                ->map(function (Employee $teacher) use ($canViewGroups, $canViewSessions, $groupCounts, $sessionCounts): array {
                    return [
                        'id' => (int) $teacher->id,
                        'name' => $teacher->full_name,
                        'group_assignments' => $canViewGroups ? (int) ($groupCounts[$teacher->id] ?? 0) : null,
                        'scheduled_sessions' => $canViewSessions ? (int) ($sessionCounts[$teacher->id] ?? 0) : null,
                    ];
                })->all();

            usort($teacherWorkload, static function (array $left, array $right): int {
                return (($right['scheduled_sessions'] ?? -1) <=> ($left['scheduled_sessions'] ?? -1))
                    ?: (($right['group_assignments'] ?? -1) <=> ($left['group_assignments'] ?? -1))
                    ?: strcasecmp($left['name'], $right['name']);
            });

            $teachersWithAssignments = collect($teacherWorkload)->filter(
                fn (array $teacher): bool => ($teacher['group_assignments'] ?? 0) > 0 || ($teacher['scheduled_sessions'] ?? 0) > 0,
            )->count();
            $teachersWithoutAssignments = count($teacherWorkload) - $teachersWithAssignments;
            $teacherWorkload = collect(array_slice($teacherWorkload, 0, 12));
        }

        return [
            'as_of' => $now->format('F j, Y'),
            'timezone' => (string) config('app.timezone'),
            'period_days' => $periodDays,
            'range_start' => $rangeStart->format('M j, Y'),
            'range_end' => $today->format('M j, Y'),
            'can_view_employees' => $canViewEmployees,
            'can_create_employees' => $canCreateEmployees,
            'can_view_groups' => $canViewGroups,
            'can_view_sessions' => $canViewSessions,
            'has_assignment_sources' => $hasAssignmentSources,
            'teacher_total' => $teacherTotal,
            'teacher_current' => $teacherCurrent,
            'teacher_deleted' => $teacherDeleted,
            'group_total' => $groupTotal,
            'groups_without_teacher' => $groupsWithoutTeacher,
            'scheduled_sessions' => $scheduledSessions,
            'assigned_sessions' => $assignedSessions,
            'unassigned_sessions' => $unassignedSessions,
            'sessions_without_teacher_role' => $sessionsWithoutTeacherRole,
            'session_trend' => $sessionTrend,
            'max_daily_sessions' => $maxDailySessions,
            'teacher_workload' => $teacherWorkload,
            'teachers_with_assignments' => $teachersWithAssignments,
            'teachers_without_assignments' => $teachersWithoutAssignments,
        ];
    }

    private function teachersQuery(bool $withTrashed = false, bool $onlyTrashed = false): Builder
    {
        $query = $onlyTrashed ? Employee::onlyTrashed() : ($withTrashed ? Employee::withTrashed() : Employee::query());

        return $this->constrainTeacherRole($query);
    }

    private function constrainTeacherRole(Builder $query): Builder
    {
        return $query->whereHas('jobDescription', fn (Builder $jobQuery) => $jobQuery->withTrashed()->where('job_title', 'Teacher'));
    }

    /** @param array<string, bool> $permissions @return array<int, string> */
    private function visibleSessionTypes(array $permissions): array
    {
        if ($permissions['session.index'] ?? false) {
            return [Session::TYPE_GROUP, Session::TYPE_INDIVIDUAL];
        }

        $types = [];
        $canViewGroup = $permissions['group_session.index'] ?? false;
        $canViewIndividual = $permissions['individual_session.index'] ?? false;

        if ($canViewGroup && ! $canViewIndividual) {
            return [Session::TYPE_GROUP];
        }

        if ($canViewIndividual && ! $canViewGroup) {
            return [Session::TYPE_INDIVIDUAL];
        }

        return [];
    }

    /** @param array<int, string> $sessionTypes */
    private function scopedSessions(array $sessionTypes): Builder
    {
        $query = Session::query();

        return $sessionTypes === [] ? $query->whereRaw('1 = 0') : $query->whereIn('session_type', $sessionTypes);
    }
}
