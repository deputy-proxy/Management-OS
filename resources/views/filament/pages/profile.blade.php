<x-filament-panels::page>

    {{-- ── Profile Info ── --}}
    <x-filament::section>
        <form wire:submit="updateProfile">
            {{ $this->profileForm }}

            <div class="mt-4 flex justify-end">
                <x-filament::button type="submit">
                    Save Profile
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- ── Password ── --}}
    <x-filament::section class="mt-6">
        <form wire:submit="updatePassword">
            {{ $this->passwordForm }}

            <div class="mt-4 flex justify-end">
                <x-filament::button type="submit" color="warning">
                    Update Password
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- ── Recent Activity ── --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Recent Activity</x-slot>
        <x-slot name="description">Your last 10 actions in the system.</x-slot>

        @php $logs = $this->getActivityLog(); @endphp

        @if ($logs->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No activity recorded yet.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($logs as $activity)
                    <li class="flex items-start gap-3 py-3">
                        <x-filament::icon
                            icon="heroicon-o-bolt"
                            class="mt-0.5 h-4 w-4 shrink-0 text-primary-500"
                        />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $activity->description }}
                                @if ($activity->subject_type)
                                    <span class="font-normal text-gray-500 dark:text-gray-400">
                                        on {{ class_basename($activity->subject_type) }}
                                        @if ($activity->subject_id)
                                            #{{ $activity->subject_id }}
                                        @endif
                                    </span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $activity->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

</x-filament-panels::page>