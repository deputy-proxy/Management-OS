<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerInteraction;
use App\Models\CustomerInvoice;
use App\Models\CustomerLead;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CustomerDashboardData
{
    /**
     * @return array{as_of: string, recent_since: string, customers: int, open_leads: int, active_contracts: int, interactions_last_30_days: int, invoice_statuses: array<int, array{status: string, count: int}>, lead_statuses: array<int, array{status: string, count: int}>, recent_interactions: Collection<int, CustomerInteraction>, recent_leads: Collection<int, CustomerLead>, follow_up_tasks: Collection<int, Task>, contract_end_dates: Collection<int, CustomerContract>}
     */
    public function summary(?CarbonImmutable $now = null, bool $includeFollowUpTasks = false, bool $viewAllTasks = false, ?int $viewerId = null, bool $includeContractReview = false): array
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
