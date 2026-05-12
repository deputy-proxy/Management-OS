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

    {{-- ── Login History ── --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Login History</x-slot>
        <x-slot name="description">Your last 10 login sessions.</x-slot>
        @php $logins = $this->getLoginHistory(); @endphp
        @if ($logins->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No login history recorded yet.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($logins as $login)
                    @unless ($login->cleared_by_user)
                        <li class="flex items-start gap-3 py-3">
                            <x-filament::icon
                                icon="{{ $login->login_successful ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle' }}"
                                class="mt-0.5 h-4 w-4 shrink-0 {{ $login->login_successful ? 'text-success-500' : 'text-danger-500' }}"
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $login->ip_address }}
                                    <span class="font-normal text-gray-500 dark:text-gray-400">
                                        &mdash; {{ $login->device_name ?? 'Unknown device' }}
                                    </span>
                                </p>
                                @if ($login->location)
                                    @php
                                        $loc = is_string($login->location)
                                            ? json_decode($login->location, true)
                                            : (array) $login->location;
                                    @endphp
                                    @if (!($loc['default'] ?? false))
                                        <p class="text-xs text-gray-400 dark:text-gray-500">
                                            <x-filament::icon icon="heroicon-o-map-pin" class="inline h-3 w-3" />
                                            {{ implode(', ', array_filter([$loc['city'] ?? null, $loc['state_name'] ?? null, $loc['country'] ?? null])) }}
                                        </p>
                                    @endif
                                @endif
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ $login->login_at?->diffForHumans() ?? $login->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <x-filament::button
                                size="xs"
                                color="gray"
                                wire:click="clearLoginEntry({{ $login->id }})"
                                wire:confirm="Are you sure you want to clear this login entry?"
                            >
                                Clear
                            </x-filament::button>
                        </li>
                    @endunless
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>