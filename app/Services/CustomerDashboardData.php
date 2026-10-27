<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerInteraction;
use App\Models\CustomerInvoice;
use App\Models\CustomerLead;
use App\Models\CustomerLifecycleEvent;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CustomerDashboardData
{
    /**
     * @return array{as_of: string, recent_since: string, customers: int, open_leads: int, active_contracts: int, interactions_last_30_days: int, invoice_statuses: array<int, array{status: string, count: int}>, lead_statuses: array<int, array{status: string, count: int}>, recent_interactions: Collection<int, CustomerInteraction>, recent_leads: Collection<int, CustomerLead>, follow_up_tasks: Collection<int, Task>, contract_end_dates: Collection<int, CustomerContract>, retention_cohorts: array{tracking_start_at: string, as_of: string, excluded_conversion_records_count: int, duplicate_conversion_leads_excluded_count: int, cohorts: array<int, array{cohort_month: string, period_month: string, cohort_size: int, active_count: int, churned_count: int, retention_percent: float, cutoff_at: string, is_partial_period: bool}>}|null}
     */
    public function summary(?CarbonImmutable $now = null, bool $includeFollowUpTasks = false, bool $viewAllTasks = false, ?int $viewerId = null, bool $includeContractReview = false, bool $includeRetentionCohorts = false): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $recentSince = $now->subDays(30);
        $monthStart = $now->startOfMonth()->toDateString();
        $monthEnd = $now->endOfMonth()->toDateString();

        $openLeads = CustomerLead::query()->whereNull('converted_customer_id');

        return [
            'as_of' => $now->format('F j, Y'),
            'recent_since' => $recentSince->format('M j, Y'),
            'customers' => Customer::query()->count(),
            'open_leads' => (clone $openLeads)->count(),
            'active_contracts' => CustomerContract::query()
                ->whereDate('start_date', '<=', $now->toDateString())
                ->whereDate('end_date', '>=', $now->toDateString())
                ->count(),
            'interactions_last_30_days' => CustomerInteraction::query()
                ->whereBetween('timestamp', [$recentSince, $now])
                ->count(),
            'invoice_statuses' => CustomerInvoice::query()
                ->whereBetween('month', [$monthStart, $monthEnd])
                ->select('status')
                ->selectRaw('COUNT(*) as record_count')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn (object $row): array => ['status' => (string) $row->status, 'count' => (int) $row->record_count])
                ->all(),
            'lead_statuses' => (clone $openLeads)
                ->select('status')
                ->selectRaw('COUNT(*) as record_count')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn (object $row): array => ['status' => (string) $row->status, 'count' => (int) $row->record_count])
                ->all(),
            'recent_interactions' => CustomerInteraction::query()
                ->with('customer:id,name')
                ->whereBetween('timestamp', [$recentSince, $now])
                ->orderByDesc('timestamp')
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'customer_id', 'reason', 'timestamp']),
            'recent_leads' => (clone $openLeads)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'full_name', 'email', 'status', 'last_contacted_at', 'created_at']),
            'follow_up_tasks' => $this->followUpTasks($includeFollowUpTasks, $viewAllTasks, $viewerId),
            'retention_cohorts' => $includeRetentionCohorts ? $this->retentionCohorts($now) : null,
            'contract_end_dates' => $includeContractReview
                ? CustomerContract::query()
                    ->with('customer:id,name')
                    ->whereNotNull('end_date')
                    ->orderBy('end_date')
                    ->orderBy('id')
                    ->limit(8)
                    ->get(['id', 'customer_id', 'contract_code', 'end_date'])
                : collect(),
        ];
    }

    /**
     * @return array{tracking_start_at: string, as_of: string, excluded_conversion_records_count: int, duplicate_conversion_leads_excluded_count: int, cohorts: array<int, array{cohort_month: string, period_month: string, cohort_size: int, active_count: int, churned_count: int, retention_percent: float, cutoff_at: string, is_partial_period: bool}>}
     */
    private function retentionCohorts(CarbonImmutable $now): array
    {
        $timezone = (string) config('customer-retention.timezone', 'Europe/Bucharest');
        $trackingStart = CarbonImmutable::parse((string) config('customer-retention.tracking_start_at'), $timezone);
        $convertedLeads = CustomerLead::query()
            ->where('status', 'converted')
            ->whereNotNull('converted_at')
            ->where('converted_at', '>=', $trackingStart->toDateTimeString())
            ->where('converted_at', '<=', $now->toDateTimeString())
            ->orderBy('converted_at')
            ->orderBy('id')
            ->get(['id', 'converted_customer_id', 'converted_at']);

        $candidateCustomerIds = $convertedLeads->pluck('converted_customer_id')->filter()->unique()->values();
        $existingCustomerIds = Customer::query()->whereIn('id', $candidateCustomerIds)->pluck('id')->flip();
        $eligibleLeads = $convertedLeads->filter(fn (CustomerLead $lead): bool => $lead->converted_customer_id !== null && $existingCustomerIds->has($lead->converted_customer_id));
        $firstLeadPerCustomer = $eligibleLeads->unique('converted_customer_id')->values();
        $customerIds = $firstLeadPerCustomer->pluck('converted_customer_id')->map(fn ($id): int => (int) $id)->all();
        $eventsByCustomer = CustomerLifecycleEvent::query()
            ->whereIn('customer_id', $customerIds)
            ->where('effective_at', '<=', $now->toDateTimeString())
            ->orderBy('effective_at')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'event_type', 'effective_at'])
            ->groupBy('customer_id');

        $cohortGroups = $firstLeadPerCustomer->groupBy(fn (CustomerLead $lead): string => CarbonImmutable::parse($lead->converted_at, $timezone)->setTimezone($timezone)->format('Y-m'));
        $nowInTimezone = $now->setTimezone($timezone);
        $rows = [];

        foreach ($cohortGroups as $cohortMonth => $leads) {
            $size = $leads->count();
            $period = CarbonImmutable::parse($cohortMonth.'-01 00:00:00', $timezone);

            while ($period->lte($nowInTimezone)) {
                $monthEnd = $period->endOfMonth();
                $isPartialPeriod = $monthEnd->gt($nowInTimezone);
                $cutoff = $isPartialPeriod ? $nowInTimezone : $monthEnd->endOfDay();
                $active = 0;
                $churned = 0;

                foreach ($leads as $lead) {
                    $latestEvent = $eventsByCustomer->get($lead->converted_customer_id)?->last(
                        fn (CustomerLifecycleEvent $event): bool => CarbonImmutable::parse($event->effective_at, $timezone)->lte($cutoff),
                    );

                    if ($latestEvent?->event_type === CustomerLifecycleEvent::TYPE_CHURNED) {
                        $churned++;
                    } else {
                        $active++;
                    }
                }

                $rows[] = [
                    'cohort_month' => $cohortMonth,
                    'period_month' => $period->format('Y-m'),
                    'cohort_size' => $size,
                    'active_count' => $active,
                    'churned_count' => $churned,
                    'retention_percent' => $size > 0 ? round($active / $size * 100, 2, PHP_ROUND_HALF_UP) : 0.0,
                    'cutoff_at' => $cutoff->toIso8601String(),
                    'is_partial_period' => $isPartialPeriod,
                ];

                $period = $period->addMonthNoOverflow()->startOfMonth();
            }
        }

        usort($rows, static fn (array $left, array $right): int => [$left['cohort_month'], $left['period_month']] <=> [$right['cohort_month'], $right['period_month']]);

        return [
            'tracking_start_at' => $trackingStart->toDateString(),
            'as_of' => $now->setTimezone($timezone)->toIso8601String(),
            'excluded_conversion_records_count' => $convertedLeads->count() - $eligibleLeads->count(),
            'duplicate_conversion_leads_excluded_count' => $eligibleLeads->count() - $firstLeadPerCustomer->count(),
            'cohorts' => $rows,
        ];
    }

    private function followUpTasks(bool $include, bool $viewAll, ?int $viewerId): Collection
    {
        if (! $include || (! $viewAll && $viewerId === null)) {
            return collect();
        }

        $query = Task::query()
            ->with('taskable')
            ->where('status', '!=', 'done')
            ->whereIn('taskable_type', [CustomerLead::class, CustomerContract::class])
            ->orderBy('due_date')
            ->orderBy('id');

        if (! $viewAll) {
            $query->where(fn ($scope) => $scope->where('assigned_to', $viewerId)->orWhere('created_by', $viewerId));
        }

        return $query->limit(8)->get(['id', 'action', 'status', 'due_date', 'assigned_to', 'created_by', 'taskable_id', 'taskable_type']);
    }
}
