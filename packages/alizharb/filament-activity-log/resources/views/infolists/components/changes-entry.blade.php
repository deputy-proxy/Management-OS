@php
    $state = $getState();
    $newValues = $state['new'] ?? [];
    $oldValues = $state['old'] ?? [];
    $allKeys = array_unique(array_merge(array_keys($newValues), array_keys($oldValues)));
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
    <table class="w-full text-sm text-left!">
        <thead class="bg-gray-50 dark:bg-gray-800 text-left!">
            <tr>
                <th class="w-1/3 px-4 py-3 text-xs text-left! font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" style="text-align: left">
                    Field
                </th>
                <th class="w-1/3 px-4 py-3 text-xs text-left! font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" style="text-align: left">
                    Old Value
                </th>
                <th class="w-1/3 px-4 py-3 text-xs text-left! font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" style="text-align: left">
                    New Value
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ($allKeys as $key)
                @php
                    $old = $oldValues[$key] ?? null;
                    $new = $newValues[$key] ?? null;
                    $changed = $old !== $new;
                @endphp

                <tr class="{{ $changed ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/50' }}">
                    <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">
                        {{ str($key)->replace('_', ' ')->title() }}
                    </td>
                    <td class="px-4 py-3 {{ $changed ? 'text-danger-600 dark:text-danger-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">
                        @if ($changed && $old === null)
                            <span class="italic text-gray-400 dark:text-gray-500">empty</span>
                        @else
                            {{ $old ?? '—' }}
                        @endif
                    </td>
                    <td class="px-4 py-3 {{ $changed ? 'text-success-600 dark:text-success-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">
                        @if ($changed && $new === null)
                            <span class="italic text-gray-400 dark:text-gray-500">empty</span>
                        @else
                            {{ $new ?? '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>