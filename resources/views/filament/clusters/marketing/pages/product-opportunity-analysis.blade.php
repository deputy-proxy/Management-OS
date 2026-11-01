<x-filament-panels::page>
    @php($analysis = $this->getAnalysisData())

    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">Scores are configurable heuristics, not observed facts or validated market opportunities. Missing or stale evidence remains unknown and is excluded from the weighted score. No live market data is fetched.</p>

        @if ($analysis['configuration_url'])
            <div><x-filament::button tag="a" :href="$analysis['configuration_url']" color="gray">Configure scoring profiles</x-filament::button></div>
        @endif

        @if (! $analysis['profile'])
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">No active scoring profile exists. A user with configuration permission must define and activate a versioned profile before scores can be calculated.</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div><p class="text-xs uppercase text-gray-500">Active scoring profile</p><p class="font-semibold">{{ $analysis['profile']['name'] }} v{{ $analysis['profile']['version'] }}</p></div>
                    <div><p class="text-xs uppercase text-gray-500">Profile status</p><p class="font-semibold">{{ $analysis['profile']['is_provisional'] ? 'Provisional' : 'Configured' }}</p></div>
                    <div><p class="text-xs uppercase text-gray-500">Products in result</p><p class="font-semibold tabular-nums">{{ number_format($analysis['count']) }}</p></div>
                </div>
                @if ($analysis['profile']['description'])<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $analysis['profile']['description'] }}</p>@endif
            </x-filament::section>

            <x-filament::section>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="md:col-span-2"><label class="text-sm font-medium" for="opportunity-search">Product or service</label><input id="opportunity-search" type="search" wire:model.live.debounce.300ms="search" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" placeholder="Search title"></div>
                    <div><label class="text-sm font-medium" for="opportunity-type">Type</label><select id="opportunity-type" wire:model.live="productTypeFilter" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option value="">All types</option>@foreach ($analysis['product_types'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label class="text-sm font-medium" for="opportunity-status">Score state</label><select id="opportunity-status" wire:model.live="statusFilter" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option value="">All states</option><option value="scored">Scored</option><option value="provisional">Provisional</option><option value="unknown">Unknown</option></select></div>
                </div>
                <div class="mt-3"><x-filament::button color="gray" size="sm" wire:click="resetFilters">Clear filters</x-filament::button></div>
            </x-filament::section>

            <x-filament::section>
                @if (count($analysis['rows']) === 0)
                    <p class="text-sm text-gray-600 dark:text-gray-300">No products or services match these filters. Products without a usable evidence signal are still listed when filters allow them.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700"><tr><th class="py-3 pr-4">Product / service</th><th class="px-3 py-3">Type</th><th class="px-3 py-3 text-right">Heuristic score</th><th class="px-3 py-3">State</th><th class="px-3 py-3">Profile</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($analysis['rows'] as $row)
                                    <tr class="align-top">
                                        <th scope="row" class="py-3 pr-4 font-medium">@if ($row['product_url'])<a class="underline" href="{{ $row['product_url'] }}">{{ $row['title'] }}</a>@else{{ $row['title'] }}@endif<p class="text-xs font-normal text-gray-500">{{ $row['product_status'] }}</p></th>
                                        <td class="px-3 py-3">{{ $analysis['product_types'][$row['product_type']] ?? ucfirst($row['product_type']) }}</td>
                                        <td class="px-3 py-3 text-right tabular-nums">{{ $row['score'] === null ? 'Unknown' : number_format($row['score'], 2).' / 100' }}</td>
                                        <td class="px-3 py-3">{{ ucfirst($row['status']) }}</td>
                                        <td class="px-3 py-3">v{{ $row['profile_version'] }}</td>
                                    </tr>
                                    <tr><td colspan="5" class="pb-4 pt-1">
                                        <details class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                                            <summary class="cursor-pointer font-medium">Score explanation and evidence</summary>
                                            <div class="mt-3 space-y-3">
                                                @foreach ($row['breakdown'] as $factor)
                                                    <div class="border-t border-gray-200 pt-3 first:border-0 first:pt-0 dark:border-gray-700">
                                                        <div class="flex flex-wrap justify-between gap-2"><p class="font-medium">{{ $factor['label'] }} <span class="text-xs font-normal text-gray-500">(weight {{ $factor['weight'] }})</span></p><p class="tabular-nums">{{ $factor['score'] === null ? 'Unknown' : number_format($factor['score'], 2).' / 100' }}</p></div>
                                                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $factor['explanation'] }} Value: {{ $factor['value'] ?? 'unknown' }}. Weighted contribution: {{ $factor['weighted_contribution'] ?? 'excluded' }}. State: {{ $factor['status'] }}. Range: {{ $factor['minimum_value'] }} to {{ $factor['maximum_value'] }}, {{ $factor['direction'] }} preferred.</p>
                                                        @if (count($factor['evidence']) > 0)
                                                            <ul class="mt-2 space-y-1 text-xs">
                                                                @foreach ($factor['evidence'] as $evidence)
                                                                    <li>
                                                                        @if ($evidence['detail_url'])<a class="underline" href="{{ $evidence['detail_url'] }}">{{ $evidence['label'] }}</a>@else{{ $evidence['label'] }}@endif
                                                                        @if ($evidence['source_url']) · <a class="underline" href="{{ $evidence['source_url'] }}" target="_blank" rel="noopener noreferrer">Source</a>@endif
                                                                        @if ($evidence['source_reference'] ?? null) · ref {{ $evidence['source_reference'] }}@endif
                                                                        @if ($evidence['source_type'] ?? null) · {{ $evidence['source_type'] }}@endif
                                                                        · observed {{ $evidence['observed_at'] ?? 'time unknown' }} · {{ $evidence['freshness'] ?? 'freshness unknown' }}
                                                                        @if (isset($evidence['confidence'])) · confidence {{ number_format((float) $evidence['confidence'] * 100, 1) }}%@endif
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    </td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Scores use the active profile version shown above. Known weight: {{ $row['known_weight'] }} of {{ $row['configured_weight'] }}. Unknown factors are not treated as zero. Only exact title matches are used for competitor-offer note evidence; no product equivalence, currency conversion, market demand, or price advantage is inferred.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
