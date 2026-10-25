<x-filament-panels::page>
    @php($summary = $this->getDashboardSummary())
    @php($urls = $this->getAuthorizedResourceUrls())

    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">Work tracking as of {{ $summary['as_of'] }}. Tasks are scoped to records you can see in the task resource; completed tasks use the existing <code>done</code> status.</p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['label' => 'Tasks matching filters', 'value' => $summary['tasks_total'], 'url' => $urls['tasks']], ['label' => 'Open tasks matching filters', 'value' => $summary['tasks_open'], 'url' => $urls['tasks']], ['label' => 'Overdue tasks', 'value' => $summary['tasks_overdue'], 'url' => $urls['tasks']], ['label' => 'Projects in scheduled date range', 'value' => $summary['projects_in_date_range'], 'url' => $urls['projects']]] as $metric)
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $metric['value'] === null ? 'Restricted' : number_format($metric['value']) }}</p>
                    @if ($metric['url'])
                        <x-filament::link class="mt-3" :href="$metric['url']">View records</x-filament::link>
                    @elseif ($metric['value'] !== null)
                        <p class="mt-3 text-xs text-gray-500">Record list restricted</p>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        @if ($summary['tasks_total'] !== null)
            <x-filament::section>
                <x-slot name="heading">Task list filters</x-slot>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Status
                        <select wire:model.live="taskStatusFilter" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">All statuses</option>
                            @foreach ($summary['task_statuses'] as $status)
                                <option value="{{ $status['status'] }}">{{ $status['status'] !== '' ? str($status['status'])->replace('_', ' ')->title() : 'Unspecified' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Assigned to
                        <select wire:model.live="taskOwnerFilter" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">All visible assignees</option>
                            @foreach ($summary['task_filter_options']['owners'] as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Project
                        <select wire:model.live="taskProjectFilter" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">All visible projects</option>
                            @foreach ($summary['task_filter_options']['projects'] as $id => $title)
                                <option value="{{ $id }}">{{ $title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Due date
                        <select wire:model.live="dueDateFilter" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="all">Overdue and next 7 days</option>
                            <option value="overdue">Overdue only</option>
                            <option value="next_7_days">Next 7 days only</option>
                        </select>
                    </label>
                </div>
            </x-filament::section>
        @endif

        @if ($summary['tasks_total'] !== null || $summary['projects_total'] !== null)
            <x-filament::section>
                <x-slot name="heading">Delivery attention</x-slot>
                <x-slot name="description">Explicit schedule and task signals from records the viewer is allowed to see. These are review prompts, not a prediction or an overall risk score.</x-slot>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @if ($summary['tasks_total'] !== null)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Overdue open tasks</p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums">{{ number_format($summary['tasks_overdue']) }}</p>
                            <p class="mt-1 text-xs text-gray-500">Due date is before today; completed tasks excluded.</p>
                        </div>
                    @endif
                    @if ($summary['projects_total'] !== null)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Projects started after planned start</p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums">{{ number_format($summary['projects_started_after_plan']) }}</p>
                            <p class="mt-1 text-xs text-gray-500">Recorded start_date is later than planned_start_date.</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Projects finished after planned end</p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums">{{ number_format($summary['projects_finished_after_plan']) }}</p>
                            <p class="mt-1 text-xs text-gray-500">Recorded end_date is later than planned_end_date.</p>
                        </div>
                    @endif
                </div>
                <p class="mt-4 text-xs text-gray-500">Next action: open the source records, confirm the planned and recorded dates, and agree corrective action with the owner. Task dependencies, blocked reasons, last meaningful update and business impact are not stored as structured task fields, so this report does not infer them.</p>
                @if ($urls['tasks'])
                    <x-filament::link class="mt-3" :href="$urls['tasks']">Review task records</x-filament::link>
                @endif
                @if ($urls['projects'])
                    <x-filament::link class="mt-3 ml-3" :href="$urls['projects']">Review project records</x-filament::link>
                @endif
            </x-filament::section>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <x-filament::section>
                <x-slot name="heading">Task status</x-slot>
                <x-slot name="description">All visible tasks grouped by stored status; this inventory is independent of list filters.</x-slot>
                @if ($summary['tasks_total'] === null)
                    <p class="text-sm text-gray-500">Task metrics are restricted.</p>
                @else
                    @forelse ($summary['task_statuses'] as $status)
                        <div class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                            <span>{{ $status['status'] !== '' ? str($status['status'])->replace('_', ' ')->title() : 'Unspecified' }}</span>
                            <span class="font-semibold tabular-nums">{{ number_format($status['count']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No visible tasks.</p>
                    @endforelse
                @endif
                @if ($urls['tasks'])
                    <x-filament::link class="mt-3" :href="$urls['tasks']">View tasks</x-filament::link>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Overdue tasks</x-slot>
                <x-slot name="description">Due before {{ $summary['as_of'] }} and not marked done.</x-slot>
                @if ($summary['tasks_total'] === null)
                    <p class="text-sm text-gray-500">Task metrics are restricted.</p>
                @elseif ($summary['overdue_tasks']->isEmpty())
                    <p class="text-sm text-gray-500">No overdue open tasks.</p>
                @else
                    @foreach ($summary['overdue_tasks'] as $task)
                        <div class="border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                            <p class="font-medium text-gray-950 dark:text-white">{{ $task->action }}</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $task->project?->title ?? 'Project unavailable' }} · {{ $task->projectPhase?->title ?? 'Phase unavailable' }}</p>
                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">Due {{ $task->due_date?->format('M j, Y') }} · {{ $task->assignedTo?->name ?? 'Unassigned' }}</p>
                        </div>
                    @endforeach
                    @if ($urls['tasks'])
                        <x-filament::link class="mt-3" :href="$urls['tasks']">View all tasks</x-filament::link>
                    @endif
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Due in the next 7 days</x-slot>
                <x-slot name="description">Open tasks due from today through {{ $summary['week_end'] }}, inclusive.</x-slot>
                @if ($summary['tasks_total'] === null)
                    <p class="text-sm text-gray-500">Task metrics are restricted.</p>
                @elseif ($summary['upcoming_tasks']->isEmpty())
                    <p class="text-sm text-gray-500">No open tasks due in this window.</p>
                @else
                    @foreach ($summary['upcoming_tasks'] as $task)
                        <div class="border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                            <p class="font-medium text-gray-950 dark:text-white">{{ $task->action }}</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $task->project?->title ?? 'Project unavailable' }} · {{ $task->projectPhase?->title ?? 'Phase unavailable' }}</p>
                            <p class="mt-1 text-xs text-gray-500">Due {{ $task->due_date?->format('M j, Y') }} · {{ $task->assignedTo?->name ?? 'Unassigned' }}</p>
                        </div>
                    @endforeach
                    @if ($urls['tasks'])
                        <x-filament::link class="mt-3" :href="$urls['tasks']">View all tasks</x-filament::link>
                    @endif
                @endif
            </x-filament::section>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([['label' => 'Projects', 'total' => $summary['projects_total'], 'current' => $summary['projects_in_date_range'], 'url' => $urls['projects'], 'description' => 'A project is in its scheduled date range when start_date is on or before today and end_date is on or after today.'], ['label' => 'Project phases', 'total' => $summary['phases_total'], 'current' => $summary['phases_in_date_range'], 'url' => $urls['phases'], 'description' => 'A phase is in its scheduled date range when start_on is on or before today and end_on is on or after today.']] as $group)
                <x-filament::section>
                    <x-slot name="heading">{{ $group['label'] }}</x-slot>
                    @if ($group['total'] === null)
                        <p class="text-sm text-gray-500">Metrics are restricted.</p>
                    @else
                        <div class="flex justify-between gap-4 py-2 text-sm"><span>Total records</span><span class="font-semibold tabular-nums">{{ number_format($group['total']) }}</span></div>
                        <div class="flex justify-between gap-4 py-2 text-sm"><span>In scheduled date range</span><span class="font-semibold tabular-nums">{{ number_format($group['current']) }}</span></div>
                        <p class="mt-2 text-xs text-gray-500">{{ $group['description'] }}</p>
                        @if ($group['url'])
                            <x-filament::link class="mt-3" :href="$group['url']">View {{ strtolower($group['label']) }}</x-filament::link>
                        @endif
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        <p class="text-xs text-gray-500">Definitions: soft-deleted tasks/projects/phases are excluded. Tasks are open when status is not exactly <code>done</code>; overdue tasks have due_date strictly before today. Due today is not overdue. Project and phase date-range counts use inclusive boundaries. Task status values come from the task form (pending, in_progress, done); project status is free text, so date-range counts are used instead of guessing which status means active. Task counts follow the task resource's assigned-to/created-by scope unless the viewer has task.view_all.</p>
    </div>
</x-filament-panels::page>
