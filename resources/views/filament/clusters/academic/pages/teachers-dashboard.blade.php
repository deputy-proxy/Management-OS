<x-filament-panels::page>
    @php($dashboard = $this->getDashboardData())

    <div class="space-y-6">
        <section aria-labelledby="teacher-population-heading" class="space-y-3">
            <div>
                <h2 id="teacher-population-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Teacher population</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">As of {{ $dashboard['as_of'] }} ({{ $dashboard['timezone'] }}). Teachers are employee records whose linked job description has the exact title “Teacher”. No active/inactive status is inferred.</p>
            </div>

            @if (! $dashboard['can_view_employees'])
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Teacher population metrics are unavailable because you do not have employee-list permission.</p>
                </x-filament::section>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-filament::section>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Total teacher records</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['teacher_total']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Exact job title match, including soft-deleted employee records.</p>
                    </x-filament::section>
                    <x-filament::section>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Current teacher records</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['teacher_current']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Teacher-role employees not soft-deleted.</p>
                    </x-filament::section>
                    <x-filament::section>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Soft-deleted teacher records</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['teacher_deleted']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Retained employee records marked deleted; not an active/inactive classification.</p>
                    </x-filament::section>
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                @if ($dashboard['employees_url'])
                    <x-filament::button tag="a" :href="$dashboard['employees_url']" icon="heroicon-m-users">View employees</x-filament::button>
                @endif
                @if ($dashboard['create_employee_url'])
                    <x-filament::button tag="a" :href="$dashboard['create_employee_url']" color="gray" icon="heroicon-m-plus">Create employee</x-filament::button>
                @endif
                @if ($dashboard['groups_url'])
                    <x-filament::button tag="a" :href="$dashboard['groups_url']" color="gray" icon="heroicon-m-user-group">View groups</x-filament::button>
                @endif
                @if ($dashboard['sessions_url'])
                    <x-filament::button tag="a" :href="$dashboard['sessions_url']" color="gray" icon="heroicon-m-calendar-days">View sessions</x-filament::button>
                @endif
            </div>
        </section>

        <section aria-labelledby="assignment-coverage-heading" class="space-y-3">
            <div>
                <h2 id="assignment-coverage-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Assignment coverage</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Session metrics use non-deleted sessions whose status is exactly <code>scheduled</code> and whose start time falls in the selected date range.</p>
            </div>
            @if (! $dashboard['can_view_sessions'] && ! $dashboard['can_view_groups'])
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Assignment coverage is unavailable because you do not have access to group or session records.</p>
                </x-filament::section>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @if ($dashboard['can_view_sessions'])
                        <x-filament::section>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Scheduled sessions in period</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['scheduled_sessions']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Exact status match; group and individual session visibility follows your session permissions.</p>
                        </x-filament::section>
                        <x-filament::section>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Sessions with a teacher ID</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['assigned_sessions']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Scheduled sessions with a non-null <code>teacher_id</code>.</p>
                        </x-filament::section>
                        <x-filament::section>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Sessions with no teacher ID</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['unassigned_sessions']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Scheduled sessions with a null <code>teacher_id</code>.</p>
                        </x-filament::section>
                    @endif
                    @if ($dashboard['can_view_groups'])
                        <x-filament::section>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Current group records</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['group_total']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Non-deleted groups; group status is free text and is not treated as active/inactive.</p>
                        </x-filament::section>
                        @if ($dashboard['can_view_employees'])
                            <x-filament::section>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Groups not linked to a current Teacher-role employee</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['groups_without_teacher']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Includes missing, soft-deleted, or non-Teacher employee relationships.</p>
                            </x-filament::section>
                        @endif
                    @endif
                    @if ($dashboard['can_view_sessions'] && $dashboard['can_view_employees'])
                        <x-filament::section>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Sessions without a current Teacher-role employee</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($dashboard['sessions_without_teacher_role']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Scheduled sessions assigned to a missing, soft-deleted, or non-Teacher employee record.</p>
                        </x-filament::section>
                    @endif
                </div>
            @endif
        </section>

        <section aria-labelledby="teacher-workload-heading" class="space-y-3">
            <div>
                <h2 id="teacher-workload-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Teacher workload</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Counts are assignments and scheduled sessions, not hours or utilization. Group assignments count current non-deleted groups; session counts use the selected period.</p>
            </div>
            <x-filament::section>
                @if (! $dashboard['can_view_employees'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Teacher-level workload is unavailable without employee-list permission.</p>
                @elseif (! $dashboard['has_assignment_sources'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Teacher records are visible, but no group or session assignment source is available to calculate workload.</p>
                @elseif ($dashboard['teacher_workload']->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-300">No current employee records have a linked job description titled exactly “Teacher”.</p>
                @else
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">{{ number_format($dashboard['teachers_with_assignments']) }} teachers have an assignment in the visible sources; {{ number_format($dashboard['teachers_without_assignments']) }} have none in those sources. Showing up to 12 teachers, ordered by scheduled sessions and then current group assignments.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="py-3 pr-4">Teacher</th>
                                    @if ($dashboard['can_view_groups'])
                                        <th scope="col" class="px-3 py-3 text-right">Current group assignments</th>
                                    @endif
                                    @if ($dashboard['can_view_sessions'])
                                        <th scope="col" class="px-3 py-3 text-right">Scheduled sessions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($dashboard['teacher_workload'] as $teacher)
                                    <tr>
                                        <th scope="row" class="py-3 pr-4 font-medium text-gray-950 dark:text-white">{{ $teacher['name'] }}</th>
                                        @if ($dashboard['can_view_groups'])
                                            <td class="px-3 py-3 text-right tabular-nums">{{ number_format($teacher['group_assignments']) }}</td>
                                        @endif
                                        @if ($dashboard['can_view_sessions'])
                                            <td class="px-3 py-3 text-right tabular-nums">{{ number_format($teacher['scheduled_sessions']) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="teaching-trend-heading" class="space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="teaching-trend-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Teaching activity trend</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Sessions with status exactly <code>scheduled</code> and a start time from {{ $dashboard['range_start'] }} through {{ $dashboard['range_end'] }}, inclusive, in {{ $dashboard['timezone'] }}.</p>
                </div>
                <div class="w-full sm:w-44">
                    <label for="teacher-trend-period" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Trend period</label>
                    <select id="teacher-trend-period" wire:model.live="period" class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                </div>
            </div>
            <x-filament::section>
                @if (! $dashboard['can_view_sessions'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Session trend is unavailable without permission to view at least one session type.</p>
                @elseif ($dashboard['scheduled_sessions'] === 0)
                    <p class="text-sm text-gray-600 dark:text-gray-300">No sessions with the exact status <code>scheduled</code> were found in the selected period.</p>
                @else
                    <div class="space-y-2" role="list" aria-label="Daily scheduled session counts for the selected period">
                        @foreach ($dashboard['session_trend'] as $day)
                            <div role="listitem" aria-label="{{ $day['label'] }}: {{ $day['count'] }} scheduled sessions" class="grid grid-cols-[4.5rem_minmax(0,1fr)_2rem] items-center gap-3">
                                <span class="text-xs text-gray-600 dark:text-gray-300">{{ $day['label'] }}</span>
                                <div class="h-3 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                                    <div class="h-full rounded bg-primary-600" style="width: {{ ($day['count'] / $dashboard['max_daily_sessions']) * 100 }}%"></div>
                                </div>
                                <span class="text-right text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $day['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="teacher-data-notes-heading" class="space-y-3">
            <h2 id="teacher-data-notes-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Data notes</h2>
            <x-filament::section>
                <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                    <li>Teacher classification is an exact match on the linked job description title <code>Teacher</code>, a field already used by employee leave forms. Other employees are not counted as teachers by inference.</li>
                    <li>Soft-deleted employee records are reported separately. The employee and group schemas do not provide a normalized active/inactive state.</li>
                    <li>Only sessions with status exactly <code>scheduled</code> are counted in date-based metrics. The status column is free text, so completed-session totals are omitted rather than guessed.</li>
                    <li>Session periods use <code>starts_at</code>, with the first selected date at midnight inclusive and midnight after today exclusive, in {{ $dashboard['timezone'] }}. Group assignments are current non-deleted group records and are not date-filtered.</li>
                    <li>Workload counts are not converted to hours, and no utilization percentage is shown because the schema does not define a teacher-capacity denominator.</li>
                    <li>Population and workload names require <code>employee.index</code>; group and session metrics and links require their own list permissions. The page gate does not grant access to these resources.</li>
                </ul>
            </x-filament::section>
        </section>
    </div>
</x-filament-panels::page>
