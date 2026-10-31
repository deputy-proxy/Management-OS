<?php

namespace App\Services;

use App\Models\CustomerInteraction;
use App\Models\Employee;
use App\Models\EmployeeTimesheet;
use App\Models\Session;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ProductRevenue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeneralOverviewData
{
    /**
     * Employees with a timesheet interval covering the current instant.
     * A timesheet is the only current presence signal represented in this schema.
     *
     * @return Collection<int, Employee>
     */
    public function employeesPresent(?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));

        $employeeIds = EmployeeTimesheet::query()
            ->whereDate('date', $now->toDateString())
            ->where('time_start', '<=', $now)
            ->where('time_end', '>', $now)
            ->distinct()
            ->pluck('employee_id');

        return Employee::query()
            ->whereIn('id', $employeeIds)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);
    }

    /**
     * Average unweighted course-level margin percentage for the current calendar month.
     * Courses without both recorded revenue and cost in the month are excluded.
     *
     * @return array{value: float|null, course_count: int, month: string}
     */
    public function averageCourseMargin(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $start = $now->startOfMonth()->toDateString();
        $end = $now->endOfMonth()->toDateString();

        $courseProductIds = Product::query()
            ->where('productable_type', 'course')
            ->pluck('id');

        $revenues = ProductRevenue::query()
            ->whereIn('product_id', $courseProductIds)
            ->whereBetween('month', [$start, $end])
            ->select('product_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('product_id')
            ->pluck('total_amount', 'product_id');

        $costs = ProductCost::query()
            ->whereIn('product_id', $courseProductIds)
            ->whereBetween('month', [$start, $end])
            ->select('product_id', DB::raw('SUM(allocated_amount) as total_amount'))
            ->groupBy('product_id')
            ->pluck('total_amount', 'product_id');

        $margins = $revenues->filter(fn ($revenue, $productId): bool => (float) $revenue > 0 && $costs->has($productId))
            ->map(fn ($revenue, $productId): float => (((float) $revenue - (float) $costs->get($productId)) / (float) $revenue) * 100);

        return [
            'value' => $margins->isEmpty() ? null : round((float) $margins->avg(), 2),
            'course_count' => $margins->count(),
            'month' => $now->format('F Y'),
        ];
    }

    /**
     * Average of recorded customer interaction ratings for the current calendar month.
     * The schema does not define the rating scale, so the result is shown without a scale label.
     *
     * @return array{value: float|null, response_count: int, month: string}
     */
    public function averageFeedback(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $query = CustomerInteraction::query()
            ->whereBetween('timestamp', [$now->startOfMonth(), $now->endOfMonth()]);

        $count = (clone $query)->whereNotNull('interaction_rating')->count();
        $average = (clone $query)->whereNotNull('interaction_rating')->avg('interaction_rating');

        return [
            'value' => $average === null ? null : round((float) $average, 2),
            'response_count' => $count,
            'month' => $now->format('F Y'),
        ];
    }

    /**
     * Calendar-week sessions, Monday through Sunday, using the configured application timezone.
     * Reads the unified Session model; session_type distinguishes group and individual sessions.
     *
     * @return Collection<int, array{type: string, title: string, teacher: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function weeklySchedule(?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $weekStart = $now->startOfWeek();
        $weekEnd = $now->endOfWeek();

        return Session::query()
            ->with(['group:id,title', 'course:id,title', 'teacher:id,full_name', 'customer:id,name'])
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->get()
            ->map(fn (Session $session): array => [
                'type' => $session->session_type === Session::TYPE_GROUP ? 'Group session' : 'Individual session',
                'title' => $session->session_type === Session::TYPE_GROUP
                    ? trim(($session->group?->title ?? 'Group') . ' · ' . ($session->course?->title ?? 'Course'))
                    : trim(($session->course?->title ?? 'Course') . ' · ' . ($session->customer?->name ?? 'Customer')),
                'teacher' => (string) ($session->teacher?->full_name ?? 'Unassigned'),
                'starts_at' => CarbonImmutable::parse($session->starts_at, config('app.timezone')),
                'ends_at' => CarbonImmutable::parse($session->ends_at, config('app.timezone')),
            ])
            ->sortBy(fn (array $session): int => $session['starts_at']->getTimestamp())
            ->values();
    }
}
