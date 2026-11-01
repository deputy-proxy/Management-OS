<x-filament-panels::page>
    @php($summary = $this->getDashboardSummary())
    @php($taskMetric = collect($summary['metrics'])->firstWhere('label', 'Open tasks'))

    <div class="space-y-6">
        <section aria-labelledby="platform-summary-heading" class="space-y-3">
            <div>
                <h2 id="platform-summary-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Platform summary</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Available record counts as of {{ $summary['as_of'] }} ({{ $summary['timezone'] }}). Counts use non-deleted records and appear only when you can access the corresponding resource.</p>
            </div>
            @if (count($summary['metrics']) > 0)
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($summary['metrics'] as $metric)
                        <x-filament::section>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $metric['label'] }}</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($metric['value']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $metric['definition'] }}</p>
                            @if ($metric['url'])
                                <x-filament::link class="mt-3" :href="$metric['url']">View records</x-filament::link>
                            @endif
                        </x-filament::section>
                    @endforeach
                </div>
            @else
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-300">No summary metrics are available for your current resource permissions.</p>
                </x-filament::section>
            @endif
        </section>

        <section aria-labelledby="executive-kpis-heading" class="space-y-3">
            <div>
                <h2 id="executive-kpis-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Executive KPI catalogue {{ $summary['kpis']['catalogue_version'] }} · {{ ucfirst($summary['kpis']['catalogue_approval_status']) }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Provisional technical definitions as of {{ $summary['kpis']['as_of'] }} ({{ $summary['kpis']['timezone'] }}). Not owner-approved; each value is shown only when its source and calculation contracts and permissions are available.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($summary['kpis']['results'] as $kpi)
                    <x-filament::section>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $kpi['label'] }}</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $kpi['state'] === 'available' ? number_format($kpi['value']) : 'Unavailable' }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $kpi['definition'] }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Source: {{ $kpi['source'] }} · Unit: {{ $kpi['unit'] }}@if ($kpi['version']) · Definition v{{ $kpi['version'] }}@endif</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Formula: {{ $kpi['formula'] ?? 'Unavailable calculation contract' }}</p>
                        @if ($kpi['unavailable_reason'])
                            <p class="mt-1 text-xs text-warning-600 dark:text-warning-400">{{ $kpi['unavailable_reason'] }}</p>
                        @endif
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Threshold state: {{ str_replace('_', ' ', $kpi['threshold_status']) }}. Informational only; no notification is implied.</p>
                    </x-filament::section>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="attention-heading" class="space-y-3">
            <div>
                <h2 id="attention-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Attention required</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Tasks due before {{ $summary['as_of'] }} that are not marked done. Task visibility follows the task resource's own scope.</p>
            </div>
            <x-filament::section>
                @if (! collect($summary['metrics'])->contains(fn ($metric) => $metric['label'] === 'Open tasks'))
                    <p class="text-sm text-gray-500 dark:text-gray-400">Task information is unavailable because you do not have task-list access.</p>
                @elseif ($summary['overdue_tasks']->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No overdue open tasks in your permitted scope.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($summary['overdue_tasks'] as $task)
                            <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $task->action }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $task->project?->title ?? 'Project unavailable' }} · {{ $task->assignedTo?->name ?? 'Unassigned' }}</p>
                                </div>
                                <p class="text-sm text-danger-600 dark:text-danger-400">Due {{ $task->due_date?->format('M j, Y') }}</p>
                            </li>
                        @endforeach
                    </ul>
                    @if ($taskMetric && $taskMetric['url'])
                        <x-filament::link class="mt-3" :href="$taskMetric['url']">View tasks</x-filament::link>
                    @endif
                @endif
            </x-filament::section>
        </section>

    </div>
</x-filament-panels::page>
