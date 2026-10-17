<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerInteraction;
use App\Models\CustomerInvoice;
use App\Models\CustomerLead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CustomerDashboardData
{
    /**
     * @return array{as_of: string, recent_since: string, customers: int, open_leads: int, active_contracts: int, interactions_last_30_days: int, invoice_statuses: array<int, array{status: string, count: int}>, lead_statuses: array<int, array{status: string, count: int}>, recent_interactions: Collection<int, CustomerInteraction>, recent_leads: Collection<int, CustomerLead>}
     */
    public function summary(?CarbonImmutable $now = null): array
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
        ];
    }
}
