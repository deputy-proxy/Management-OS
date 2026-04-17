{{-- resources/views/filament/tasks/task-list.blade.php --}}
<div class="space-y-2">
    @forelse($tasks as $task)
        <div class="flex items-start justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col gap-1">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ $task->action }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $task->description }}
                </span>
                <div class="flex flex-wrap gap-2 mt-1">
                    <span class="text-xs px-2 py-0.5 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                        {{ $task->status }}
                    </span>
                    @if($task->due_date)
                        <span class="text-xs text-gray-400">
                            Due: {{ $task->due_date->format('M d, Y') }}
                        </span>
                    @endif
                    @if($task->assignedTo)
                        <span class="text-xs text-gray-400">
                            → {{ $task->assignedTo->name }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">No tasks yet.</p>
    @endforelse
</div>