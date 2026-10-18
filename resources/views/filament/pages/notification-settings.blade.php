<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            Preferences are opt-in. Only notification events for models your current role can view are listed. These settings never grant access to a model or record. If your role changes, unavailable model preferences stop applying automatically.
        </div>

        @if (count($events) === 0)
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                No notification events are available for your current role.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3">Event</th>
                                <th class="px-4 py-3 text-center">In-app</th>
                                <th class="px-4 py-3 text-center">Email</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($events as $index => $event)
                                <tr wire:key="notification-event-{{ $event['event_key'] }}">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $event['label'] }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ $event['event_key'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" wire:model="preferences.{{ $index }}.in_app" aria-label="In-app: {{ $event['label'] }}" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" wire:model="preferences.{{ $index }}.email" aria-label="Email: {{ $event['label'] }}" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end border-t border-gray-200 p-4 dark:border-gray-700">
                    <x-filament::button wire:click="save" icon="heroicon-o-check">Save preferences</x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
