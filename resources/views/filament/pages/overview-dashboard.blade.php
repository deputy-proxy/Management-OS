<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->getOverviewItems() as $item)
            @php
                $pageClass = $item['page'];
                $canOpen = $this->canOpenOverview($pageClass);
            @endphp

            <x-filament::section>
                <div class="flex h-full flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ $item['title'] }}
                        </h2>
                        <x-filament::badge :color="$canOpen ? 'success' : 'gray'">
                            {{ $canOpen ? 'Available' : ($item['scope'] === 'Available' ? 'Restricted' : 'Planned') }}
                        </x-filament::badge>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $item['description'] }}
                    </p>

                    <p class="mt-auto text-xs text-gray-500 dark:text-gray-400">
                        Owner: {{ $item['owner'] }}
                    </p>

                    @if ($canOpen)
                        <x-filament::link :href="$pageClass::getUrl()" icon="heroicon-m-arrow-top-right-on-square">
                            Open overview
                        </x-filament::link>
                    @else
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $item['scope'] === 'Available' ? 'You do not have permission to open this overview.' : 'This overview is tracked in its own issue.' }}
                        </span>
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>