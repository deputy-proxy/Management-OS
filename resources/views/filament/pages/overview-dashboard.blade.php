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

        <section aria-labelledby="activity-heading" class="space-y-3">
            <div>
                <h2 id="activity-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Recent activity</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Latest activity-log entries for resource types you are permitted to view. Details and changed attributes are intentionally not exposed here.</p>
            </div>
            <x-filament::section>
                @if (! $summary['activity_available'])
                    <p class="text-sm text-gray-500 dark:text-gray-400">Activity is unavailable because no supported resource list permission is granted.</p>
                @elseif ($summary['recent_activity']->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No activity has been recorded for the permitted resource types.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($summary['recent_activity'] as $activity)
                            <li class="py-3">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $activity->description }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $activity->created_at?->timezone($summary['timezone'])->format('M j, Y H:i') }} · {{ class_basename($activity->subject_type) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="domain-overviews-heading" class="space-y-3">
            <div>
                <h2 id="domain-overviews-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Domain overviews</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Open a domain dashboard for its detailed operational analysis. Each destination enforces its own access check.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($this->getOverviewItems() as $item): ?>
                    @php
                        $pageClass = $item['page'];
                        $canOpen = $this->canOpenOverview($pageClass);
                        $overviewStatus = $canOpen ? 'Available' : ($item['scope'] === 'Available' ? 'Restricted' : 'Planned');
                        $overviewAction = $canOpen
                            ? 'Open overview'
                            : ($item['scope'] === 'Available'
                                ? 'You do not have permission to open this overview.'
                                : 'This overview is tracked in its own issue.');
                    @endphp
                    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex h-full flex-col gap-3">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</h3>
                                <span class="fi-badge inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $canOpen ? 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' : 'bg-gray-50 text-gray-700 dark:bg-white/5 dark:text-gray-300' }}">
                                    {{ $overviewStatus }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $item['description'] }}</p>
                            <p class="mt-auto text-xs text-gray-500 dark:text-gray-400">Owner: {{ $item['owner'] }}</p>
                            <a
                                href="{{ $canOpen ? $pageClass::getUrl() : '#' }}"
                                aria-disabled="{{ $canOpen ? 'false' : 'true' }}"
                                class="text-sm font-medium text-primary-600 dark:text-primary-400 {{ $canOpen ? 'hover:underline' : 'pointer-events-none opacity-70' }}"
                            >{{ $overviewAction }}</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</x-filament-panels::page>
