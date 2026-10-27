<x-filament-panels::page>
    @php($summary = $this->getDashboardSummary())
    @php($urls = $this->getAuthorizedResourceUrls())

    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">Customer and lead operations as of {{ $summary['as_of'] }}. Converted leads are tracked through their customer record and are excluded from lead counts to avoid double-counting.</p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['label' => 'Customers', 'value' => $summary['customers'], 'url' => $urls['customers']], ['label' => 'Unconverted leads', 'value' => $summary['open_leads'], 'url' => $urls['leads']], ['label' => 'Active contracts', 'value' => $summary['active_contracts'], 'url' => $urls['contracts']], ['label' => 'Interactions, last 30 days', 'value' => $summary['interactions_last_30_days'], 'url' => $urls['interactions']]] as $metric)
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($metric['value']) }}</p>
                    @if ($metric['url'])
                        <x-filament::link class="mt-3" :href="$metric['url']">View records</x-filament::link>
                    @else
                        <p class="mt-3 text-xs text-gray-500">Record list restricted</p>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Unconverted lead status</x-slot>
                <x-slot name="description">Counts by stored status, excluding leads already linked to a converted customer.</x-slot>
                @forelse ($summary['lead_statuses'] as $status)
                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span>{{ $status['status'] !== '' ? $status['status'] : 'Unspecified' }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($status['count']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No unconverted leads.</p>
                @endforelse
            </x-filament::section>

            @if ($summary['retention_cohorts'] !== null)
                <x-filament::section>
                    <x-slot name="heading">Customer retention cohorts</x-slot>
                    <x-slot name="description">As of {{ $summary['retention_cohorts']['as_of'] }}. Cohort month uses the earliest verified conversion per customer; tracking begins {{ $summary['retention_cohorts']['tracking_start_at'] }}.</x-slot>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Excluded conversion records with missing/deleted customer links: {{ number_format($summary['retention_cohorts']['excluded_conversion_records_count']) }}. Duplicate conversion leads excluded from customer counts: {{ number_format($summary['retention_cohorts']['duplicate_conversion_leads_excluded_count']) }}. Retained means no explicit churn event at cutoff or explicit reactivation after churn.</p>
                    @if (count($summary['retention_cohorts']['cohorts']) === 0)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">No verified conversion cohorts exist in the active tracking window yet.</p>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400"><tr><th scope="col" class="py-3 pr-4">Cohort</th><th scope="col" class="px-3 py-3">Period</th><th scope="col" class="px-3 py-3 text-right">Customers</th><th scope="col" class="px-3 py-3 text-right">Active</th><th scope="col" class="px-3 py-3 text-right">Churned</th><th scope="col" class="px-3 py-3 text-right">Retention</th><th scope="col" class="px-3 py-3">Cutoff</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($summary['retention_cohorts']['cohorts'] as $cohort)
                                        <tr><th scope="row" class="py-3 pr-4 font-medium">{{ $cohort['cohort_month'] }}</th><td class="px-3 py-3">{{ $cohort['period_month'] }} @if ($cohort['is_partial_period'])<span class="text-xs text-gray-500">partial</span>@endif</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($cohort['cohort_size']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($cohort['active_count']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($cohort['churned_count']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($cohort['retention_percent'], 2) }}%</td><td class="px-3 py-3">{{ $cohort['cutoff_at'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            <x-filament::section>
                <x-slot name="heading">Customer invoice status</x-slot>
                <x-slot name="description">Invoice counts for the current calendar month, grouped by stored status.</x-slot>
                @forelse ($summary['invoice_statuses'] as $status)
                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span>{{ $status['status'] !== '' ? $status['status'] : 'Unspecified' }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($status['count']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No customer invoices for this month.</p>
                @endforelse
                @if ($urls['invoices'])
                    <x-filament::link class="mt-3" :href="$urls['invoices']">View invoices</x-filament::link>
                @endif
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Recent customer interactions</x-slot>
                <x-slot name="description">Interactions recorded in the last 30 days, newest first ({{ $summary['recent_since'] }} onward).</x-slot>
                @forelse ($summary['recent_interactions'] as $interaction)
                    <div class="border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                        <div class="flex flex-wrap justify-between gap-2">
                            <p class="font-medium text-gray-950 dark:text-white">{{ $interaction->customer?->name ?? 'Customer unavailable' }}</p>
                            <time class="text-xs text-gray-500">{{ $interaction->timestamp }}</time>
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $interaction->reason }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No interactions recorded in this period.</p>
                @endforelse
                @if ($urls['interactions'])
                    <x-filament::link class="mt-3" :href="$urls['interactions']">View interactions</x-filament::link>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Recent unconverted leads</x-slot>
                <x-slot name="description">The eight most recently created leads that have not been converted.</x-slot>
                @forelse ($summary['recent_leads'] as $lead)
                    <div class="border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                        <p class="font-medium text-gray-950 dark:text-white">{{ $lead->full_name }}</p>
                        <div class="mt-1 flex flex-wrap justify-between gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <span>{{ $lead->status ?: 'Unspecified status' }}</span>
                            <span>{{ $lead->email }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Created {{ $lead->created_at?->format('M j, Y') ?? 'date unavailable' }} · Last contacted {{ $lead->last_contacted_at?->format('M j, Y') ?? 'not recorded' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No unconverted leads.</p>
                @endforelse
                @if ($urls['leads'])
                    <x-filament::link class="mt-3" :href="$urls['leads']">View leads</x-filament::link>
                @endif
            </x-filament::section>
        </div>

        @if ($urls['tasks'])
            <x-filament::section>
                <x-slot name="heading">Open customer follow-up tasks</x-slot>
                <x-slot name="description">Undone tasks attached to leads or customer contracts, ordered by due date. Only tasks in your permitted task scope are shown.</x-slot>
                @forelse ($summary['follow_up_tasks'] as $task)
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                        <div>
                            <p class="font-medium text-gray-950 dark:text-white">{{ $task->action }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                @if ($task->taskable instanceof \App\Models\CustomerLead)
                                    Lead: {{ $task->taskable->full_name ?: 'Lead unavailable' }}
                                @elseif ($task->taskable instanceof \App\Models\CustomerContract)
                                    Contract: {{ $task->taskable->contract_code }}
                                @else
                                    Customer record unavailable
                                @endif
                            </p>
                        </div>
                        <div class="text-right text-sm">
                            <p class="font-medium {{ $task->due_date && $task->due_date->lt(today()) ? 'text-danger-600' : 'text-gray-700 dark:text-gray-200' }}">Due {{ $task->due_date?->format('M j, Y') ?? 'date unavailable' }}</p>
                            <p class="text-xs text-gray-500">{{ $task->due_date && $task->due_date->lt(today()) ? 'Overdue' : ($task->due_date && $task->due_date->isToday() ? 'Due today' : 'Upcoming') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No open follow-up tasks in your permitted scope.</p>
                @endforelse
                <x-filament::link class="mt-3" :href="$urls['tasks']">View tasks</x-filament::link>
            </x-filament::section>
        @endif

        @if ($urls['contracts'])
            <x-filament::section>
                <x-slot name="heading">Contract end dates for review</x-slot>
                <x-slot name="description">Contracts ordered by recorded end date. An end date alone does not establish renewal, cancellation, or churn.</x-slot>
                @forelse ($summary['contract_end_dates'] as $contract)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                        <div>
                            <p class="font-medium text-gray-950 dark:text-white">{{ $contract->contract_code }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $contract->customer?->name ?? 'Customer unavailable' }}</p>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-200">Ends {{ $contract->end_date?->format('M j, Y') ?? 'date unavailable' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No contract end dates recorded.</p>
                @endforelse
                <x-filament::link class="mt-3" :href="$urls['contracts']">View contracts</x-filament::link>
            </x-filament::section>
        @endif

        <p class="text-xs text-gray-500">Definitions: customers and leads exclude soft-deleted rows; converted leads are excluded from lead metrics; active contracts have a start date on or before today and an end date on or after today; interaction activity uses the interaction timestamp; invoice periods use the stored billing month. Invoice and lead statuses are displayed as stored because the schema does not define a controlled status vocabulary.</p>
    </div>
</x-filament-panels::page>
