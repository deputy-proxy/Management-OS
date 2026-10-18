<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentGroupMembership;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class StudentsDashboardData
{
    /**
     * @return array<string, mixed>
     */
    public function summary(
        ?CarbonImmutable $now = null,
        bool $canViewStudents = true,
        bool $canCreateStudents = false,
        bool $canViewTasks = false,
        bool $canViewAllTasks = false,
        string $period = '30',
    ): array {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $periodDays = in_array($period, ['7', '30', '90'], true) ? (int) $period : 30;
        $today = $now->startOfDay();
        $rangeStart = $today->subDays($periodDays - 1);
        $rangeEnd = $today->addDay();

        $totalStudents = $canViewStudents ? Student::withTrashed()->count() : null;
        $currentStudents = $canViewStudents ? Student::query()->count() : null;
        $deletedStudents = $canViewStudents ? Student::onlyTrashed()->count() : null;

        $currentMemberships = null;
        $groupsWithCurrentMemberships = null;
        if ($canViewStudents) {
            $memberships = StudentGroupMembership::query()
                ->whereDate('started_on', '<=', $today->toDateString())
                ->where(fn (Builder $query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $today->toDateString()));
            $currentMemberships = (clone $memberships)->count();
            $groupsWithCurrentMemberships = (clone $memberships)->distinct('group_id')->count('group_id');
        }

        $enrollmentTrend = collect();
        $recentStudents = collect();
        if ($canViewStudents) {
            $dailyCounts = Student::withTrashed()
                ->where('created_at', '>=', $rangeStart)
                ->where('created_at', '<', $rangeEnd)
                ->selectRaw('DATE(created_at) as enrollment_date, COUNT(*) as record_count')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('enrollment_date')
                ->pluck('record_count', 'enrollment_date');

            $enrollmentTrend = collect(range(0, $periodDays - 1))
                ->map(function (int $offset) use ($rangeStart, $dailyCounts): array {
                    $date = $rangeStart->addDays($offset)->toDateString();

                    return [
                        'date' => $date,
                        'label' => CarbonImmutable::parse($date, config('app.timezone'))->format('M j'),
                        'count' => (int) ($dailyCounts[$date] ?? 0),
                    ];
                });

            $recentStudents = Student::query()
                ->where('created_at', '>=', $rangeStart)
                ->where('created_at', '<', $rangeEnd)
                ->latest('created_at')->latest('id')
                ->limit(5)
                ->get(['id', 'full_name', 'created_at']);
        }

        $overdueStudentTasks = collect();
        if ($canViewStudents && $canViewTasks) {
            $tasks = Task::query()
                ->with(['taskable:id,full_name', 'assignedTo:id,name'])
                ->where('taskable_type', (new Student)->getMorphClass())
                ->whereDate('due_date', '<', $today->toDateString())
                ->where('status', '!=', 'done')
                ->whereHasMorph('taskable', [Student::class], fn (Builder $query) => $query->whereNull('deleted_at'));

            if (! $canViewAllTasks) {
                if (auth()->check()) {
                    $userId = auth()->id();
                    $tasks->where(fn (Builder $query) => $query
                        ->where('assigned_to', $userId)
                        ->orWhere('created_by', $userId));
                } else {
                    $tasks->whereRaw('1 = 0');
                }
            }

            $overdueStudentTasks = $tasks
                ->orderBy('due_date')->orderBy('id')->limit(8)
                ->get(['id', 'action', 'due_date', 'assigned_to', 'taskable_id', 'taskable_type']);
        }

        return [
            'as_of' => $now->format('F j, Y'),
            'timezone' => (string) config('app.timezone'),
            'period_days' => $periodDays,
            'range_start' => $rangeStart->format('M j, Y'),
            'range_end' => $today->format('M j, Y'),
            'can_view_students' => $canViewStudents,
            'can_create_students' => $canCreateStudents,
            'can_view_tasks' => $canViewStudents && $canViewTasks,
            'total_students' => $totalStudents,
            'current_students' => $currentStudents,
            'deleted_students' => $deletedStudents,
            'current_group_memberships' => $currentMemberships,
            'groups_with_current_memberships' => $groupsWithCurrentMemberships,
            'enrollment_trend' => $enrollmentTrend,
            'max_daily_enrollments' => max(1, (int) $enrollmentTrend->max('count')),
            'recent_students' => $recentStudents,
            'overdue_student_tasks' => $overdueStudentTasks,
        ];
    }
}
