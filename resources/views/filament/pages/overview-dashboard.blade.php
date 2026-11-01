<x-filament-panels::page>
    @php($summary = $this->getDashboardSummary())
    @php($taskMetric = collect($summary['metrics'])->firstWhere('label', 'Open tasks'))
    @php($availabilityLabels = [
        'available' => 'Available',
        'unavailable_permission_denied' => 'Unavailable: source permission denied',
        'unavailable_viewer_context' => 'Unavailable: viewer context is required',
        'unavailable_invalid_definition' => 'Unavailable: KPI definition is invalid',
        'unavailable_missing_source' => 'Unavailable: source is not registered',
        'unavailable_missing_calculation' => 'Unavailable: calculation is not registered',
    ])

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
                <p class="text-sm text-gray-600 dark:text-gray-300">Reporting period: <span class="font-medium">Daily as-of snapshot</span>. This evaluates current source state against the reporting date; historical reconstruction and date-range selection are not supported by catalogue v1.</p>
            </div>

            <div wire:loading.delay role="status" aria-live="polite" class="text-sm text-gray-500 dark:text-gray-400">
                Refreshing dashboard data…
            </div>

            @if (($summary['kpis']['status'] ?? 'available') === 'error')
                <x-filament::section>
                    <p role="alert" class="text-sm text-danger-600 dark:text-danger-400">KPI data is temporarily unavailable. Other dashboard sections remain available.</p>
                </x-filament::section>
            @elseif (count($summary['kpis']['results']) === 0)
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-300">No KPI definitions are currently registered.</p>
                </x-filament::section>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($summary['kpis']['results'] as $kpi)
                        <x-filament::section>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $kpi['label'] }}</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $kpi['state'] === 'available' ? number_format($kpi['value']) : 'Unavailable' }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $kpi['definition'] }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Source: {{ $kpi['source'] }} · Unit: {{ $kpi['unit'] }}@if ($kpi['version']) · Definition v{{ $kpi['version'] }}@endif</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Formula: {{ $kpi['formula'] ?? 'Unavailable calculation contract' }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Availability: {{ $availabilityLabels[$kpi['availability']] ?? str_replace('_', ' ', $kpi['availability']) }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Source freshness: {{ str_replace('_', ' ', $kpi['freshness']['status'] ?? 'unknown') }}. Evaluated at {{ $kpi['freshness']['evaluated_at'] ?? 'unknown' }}. Source update time is not available.</p>
                            @if ($kpi['unavailable_reason'])
                                <p class="mt-1 text-xs text-warning-600 dark:text-warning-400">{{ $kpi['unavailable_reason'] }}</p>
                            @endif
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Threshold state: {{ str_replace('_', ' ', $kpi['threshold_status']) }}. Informational only; no notification is implied.</p>

                            @if ($kpi['source_url'])
                                <x-filament::link class="mt-3" :href="$kpi['source_url']">Open source list</x-filament::link>
                            @endif
                            @if (count($kpi['source_record_ids']) > 0)
                                <details class="mt-3 text-xs text-gray-600 dark:text-gray-300">
                                    <summary class="cursor-pointer font-medium">Source record IDs ({{ count($kpi['source_record_ids']) }}{{ $kpi['source_record_ids_truncated'] ? '+' : '' }})</summary>
                                    <p class="mt-1">Ordered, permission-scoped provenance IDs. The list is capped at 100 records; the KPI count remains exact.</p>
                                    <ul class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($kpi['source_record_ids'] as $recordId)
                                            <li class="rounded bg-gray-100 px-2 py-1 font-mono dark:bg-gray-800">{{ $recordId }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </x-filament::section>
                    @endforeach
                </div>
            @endif
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
