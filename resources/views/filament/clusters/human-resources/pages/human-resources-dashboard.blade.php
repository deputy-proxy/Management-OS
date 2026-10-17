<x-filament-panels::page>
    @php
        $overview = app(\App\Services\GeneralOverviewData::class);
        $presence = $overview->employeesPresent();
        $courseMargin = $overview->averageCourseMargin();
        $feedback = $overview->averageFeedback();
        $schedule = $overview->weeklySchedule();
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <x-filament::section>
            <x-slot name="heading">Employee presence</x-slot>
            <p class="text-3xl font-semibold text-gray-950 dark:text-white">{{ $presence->count() }}</p>
            <p class="text-sm text-gray-600 dark:text-gray-300">Employees with a timesheet interval active right now.</p>
            @if ($presence->isNotEmpty())
                <ul class="mt-3 list-inside list-disc text-sm text-gray-700 dark:text-gray-200">
                    @foreach ($presence->take(6) as $employee)
                        <li>{{ $employee->full_name }}</li>
                    @endforeach
                </ul>
                @if ($presence->count() > 6)
                    <p class="mt-2 text-xs text-gray-500">And {{ $presence->count() - 6 }} more.</p>
                @endif
            @else
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No active employee timesheets found.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Average course margin</x-slot>
            <p class="text-3xl font-semibold text-gray-950 dark:text-white">
                {{ $courseMargin['value'] === null ? 'N/A' : number_format($courseMargin['value'], 2) . '%' }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-300">Unweighted average of (monthly course revenue − allocated cost) ÷ revenue.</p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $courseMargin['month'] }} · {{ $courseMargin['course_count'] }} courses with both revenue and cost.</p>
            @if ($courseMargin['value'] === null)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No courses have complete revenue and cost data for this month.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Average feedback</x-slot>
            <p class="text-3xl font-semibold text-gray-950 dark:text-white">
                {{ $feedback['value'] === null ? 'N/A' : number_format($feedback['value'], 2) }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-300">Average recorded customer interaction rating.</p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $feedback['month'] }} · {{ $feedback['response_count'] }} rated interactions.</p>
            @if ($feedback['value'] === null)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No ratings recorded for this month.</p>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Weekly schedule</x-slot>
        <x-slot name="description">Sessions scheduled from Monday through Sunday in the application timezone.</x-slot>
        @if ($schedule->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No group or individual sessions are scheduled for this week.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="px-3 py-2 font-medium">When</th>
                            <th class="px-3 py-2 font-medium">Session</th>
                            <th class="px-3 py-2 font-medium">Type</th>
                            <th class="px-3 py-2 font-medium">Teacher</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($schedule as $session)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <div>{{ $session['starts_at']->format('D, M j') }}</div>
                                    <div class="text-xs text-gray-500">{{ $session['starts_at']->format('H:i') }}–{{ $session['ends_at']->format('H:i') }}</div>
                                </td>
                                <td class="px-3 py-2">{{ $session['title'] }}</td>
                                <td class="px-3 py-2">{{ $session['type'] }}</td>
                                <td class="px-3 py-2">{{ $session['teacher'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
