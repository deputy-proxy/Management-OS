<x-filament-panels::page>
    @php($dashboard = $this->getDashboardData())

    <div class="space-y-6">
        <section aria-labelledby="student-population-heading" class="space-y-3">
            <div>
                <h2 id="student-population-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Student population</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">As of {{ $dashboard['as_of'] }} ({{ $dashboard['timezone'] }}). The schema has no explicit active/inactive student status, so counts describe records rather than student activity.</p>
            </div>

            @if (! $dashboard['can_view_students'])
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Student metrics are unavailable because you do not have permission to view student records.</p>
                </x-filament::section>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-filament::section>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Total student records</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['total_students']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">All records, including soft-deleted records.</p>
                    </x-filament::section>
                    <x-filament::section>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Current records</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['current_students']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Records not soft-deleted.</p>
                    </x-filament::section>
                    <x-filament::section>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Soft-deleted records</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['deleted_students']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Retained records marked deleted; not interpreted as inactive students.</p>
                    </x-filament::section>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($dashboard['students_url'])
                        <x-filament::button tag="a" :href="$dashboard['students_url']" icon="heroicon-m-users">View students</x-filament::button>
                    @endif
                    @if ($dashboard['create_student_url'])
                        <x-filament::button tag="a" :href="$dashboard['create_student_url']" color="gray" icon="heroicon-m-plus">Create student</x-filament::button>
                    @endif
                </div>
            @endif
        </section>

        <section aria-labelledby="enrollment-trend-heading" class="space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="enrollment-trend-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Enrollment trend</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Student records created from {{ $dashboard['range_start'] }} through {{ $dashboard['range_end'] }}, inclusive, using {{ $dashboard['timezone'] }}. Later soft deletion does not erase a historical record creation.</p>
                </div>
                <div class="w-full sm:w-44">
                    <label for="student-trend-period" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Trend period</label>
                    <select id="student-trend-period" wire:model.live="period" class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                </div>
            </div>
            <x-filament::section>
                @if (! $dashboard['can_view_students'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Enrollment data is unavailable without student-list permission.</p>
                @elseif ($dashboard['total_students'] === 0)
                    <p class="text-sm text-gray-600 dark:text-gray-300">No student records exist yet. The selected period currently contains zero enrollments.</p>
                @else
                    <div class="space-y-2" role="list" aria-label="Daily student record creation counts for the selected period">
                        @foreach ($dashboard['enrollment_trend'] as $day)
                            <div role="listitem" aria-label="{{ $day['label'] }}: {{ $day['count'] }} student records created" class="grid grid-cols-[4.5rem_minmax(0,1fr)_2rem] items-center gap-3">
                                <span class="text-xs text-gray-600 dark:text-gray-300">{{ $day['label'] }}</span>
                                <div class="h-3 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                                    <div class="h-full rounded bg-primary-600" style="width: {{ ($day['count'] / $dashboard['max_daily_enrollments']) * 100 }}%"></div>
                                </div>
                                <span class="text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $day['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="student-follow-up-heading" class="space-y-3">
            <div>
                <h2 id="student-follow-up-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Follow-up required</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Overdue, unfinished tasks explicitly linked to current student records. Visibility follows task permissions.</p>
            </div>
            <x-filament::section>
                @if (! $dashboard['can_view_students'] || ! $dashboard['can_view_tasks'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">The follow-up queue is unavailable without both student-list and task-list permissions.</p>
                @elseif ($dashboard['overdue_student_tasks']->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-300">No overdue student-linked tasks were found in your permitted scope.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($dashboard['overdue_student_tasks'] as $task)
                            <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $task->action }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $task->taskable?->full_name ?? 'Student unavailable' }} · {{ $task->assignedTo?->name ?? 'Unassigned' }}</p>
                                </div>
                                <p class="text-sm text-danger-600 dark:text-danger-400">Due {{ $task->due_date?->format('M j, Y') }}</p>
                            </li>
                        @endforeach
                    </ul>
                    @if ($dashboard['tasks_url'])
                        <x-filament::link class="mt-3" :href="$dashboard['tasks_url']">View tasks</x-filament::link>
                    @endif
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="recent-students-heading" class="space-y-3">
            <div>
                <h2 id="recent-students-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Recent student additions</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Up to five current student records created within the selected trend period.</p>
            </div>
            <x-filament::section>
                @if (! $dashboard['can_view_students'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Recent student records are unavailable without student-list permission.</p>
                @elseif ($dashboard['recent_students']->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-300">No current student records were added during the selected period.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($dashboard['recent_students'] as $student)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <span class="font-medium text-gray-950 dark:text-white">{{ $student->full_name }}</span>
                                <time class="shrink-0 text-sm text-gray-500 dark:text-gray-400" datetime="{{ $student->created_at?->toIso8601String() }}">{{ $student->created_at?->timezone($dashboard['timezone'])->format('M j, Y') }}</time>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="student-data-notes-heading" class="space-y-3">
            <h2 id="student-data-notes-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Data notes</h2>
            <x-filament::section>
                <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                    <li>The student schema has no explicit active/inactive, cohort, program, level, or location fields. Those breakdowns are omitted rather than inferred.</li>
                    <li>Gender is free text, so it is not treated as a normalized distribution category.</li>
                    <li>Enrollment trend counts all student records created in the selected period, including records later soft-deleted. The recent-additions list includes current records only.</li>
                    <li>Student metrics and quick links require <code>student.index</code>; creation requires <code>student.create</code>. The dashboard gate alone does not grant resource access.</li>
                </ul>
            </x-filament::section>
        </section>
    </div>
</x-filament-panels::page>
