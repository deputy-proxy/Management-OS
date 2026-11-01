<x-filament-panels::page>
    @php($dashboard = $this->getDashboardData())

    <div class="space-y-6">
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Stored competitor records as of {{ $dashboard['as_of'] }} ({{ $dashboard['timezone'] }}). This dashboard uses local records only; it does not fetch live market data.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @if ($dashboard['competitors']['index_url'])
                    <x-filament::button tag="a" :href="$dashboard['competitors']['index_url']">View competitors</x-filament::button>
                @endif
                @if ($dashboard['competitors']['create_url'])
                    <x-filament::button tag="a" :href="$dashboard['competitors']['create_url']" color="gray">Add competitor</x-filament::button>
                @endif
                @if ($dashboard['offers']['index_url'])
                    <x-filament::button tag="a" :href="$dashboard['offers']['index_url']" color="gray">View competitor offers</x-filament::button>
                @endif
                @if ($dashboard['offers']['create_url'])
                    <x-filament::button tag="a" :href="$dashboard['offers']['create_url']" color="gray">Add offer</x-filament::button>
                @endif
            </div>
        </div>

        <section aria-labelledby="competitor-inventory-heading" class="space-y-3">
            <h2 id="competitor-inventory-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Competitor inventory and profile coverage</h2>
            <x-filament::section>
                @if (! $dashboard['competitors']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Unavailable without competitor-list permission.</p>
                @else
                    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Current competitors</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($dashboard['competitors']['current_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Soft-deleted records</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($dashboard['competitors']['soft_deleted_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">With locations</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($dashboard['competitors']['with_locations_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Without locations</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($dashboard['competitors']['without_locations_count']) }}</p></div>
                    </div>
                    <div class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <h3 class="font-medium text-gray-950 dark:text-white">Profile fields populated</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Populated does not mean valid or recently verified.</p>
                        <ul class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            @foreach ($dashboard['competitors']['profile_fields'] as $field)
                                <li class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900"><span class="text-sm text-gray-600 dark:text-gray-300">{{ $field['label'] }}</span><p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($field['count']) }}</p></li>
                            @endforeach
                        </ul>
                    </div>
                    @if ($dashboard['competitors']['latest_record_edit'])
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Latest competitor record edit: {{ $dashboard['competitors']['latest_record_edit'] }}. This is not a source verification date.</p>
                    @endif
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="offer-inventory-heading" class="space-y-3">
            <h2 id="offer-inventory-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Competitor offer inventory</h2>
            <x-filament::section>
                @if (! $dashboard['offers']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Unavailable without competitor-offer list permission.</p>
                @else
                    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Current offers</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['current_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Soft-deleted offers</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['soft_deleted_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Checked in last 30 days</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['checked_recently_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Stale checks</p><p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['stale_count']) }}</p><p class="text-xs text-gray-500 dark:text-gray-400">Before {{ $dashboard['freshness_start'] }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Future-dated checks</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['future_dated_count']) }}</p></div>
                        @if ($dashboard['competitors']['can_view'])
                            <div><p class="text-sm text-gray-600 dark:text-gray-300">Offers without a current competitor</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['without_current_competitor_count']) }}</p></div>
                        @endif
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">With our similar offer noted</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['with_similar_offer_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">With differentiator noted</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['with_differentiator_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">With both notes</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['with_both_notes_count']) }}</p></div>
                    </div>
                    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-gray-950 dark:text-white">Stored status values</h3>
                            @if (count($dashboard['offers']['status_buckets']) === 0)
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No current offers have a stored status.</p>
                            @else
                                <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto">
                                    @foreach ($dashboard['offers']['status_buckets'] as $bucket)
                                        <li class="flex justify-between gap-3 text-sm"><span class="break-words">{{ $bucket['value'] }}</span><span class="shrink-0 tabular-nums">{{ number_format($bucket['count']) }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-950 dark:text-white">Stored offer-type values</h3>
                            @if (count($dashboard['offers']['offer_type_buckets']) === 0)
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No current offers have an offer type.</p>
                            @else
                                <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto">
                                    @foreach ($dashboard['offers']['offer_type_buckets'] as $bucket)
                                        <li class="flex justify-between gap-3 text-sm"><span class="break-words">{{ $bucket['value'] }}</span><span class="shrink-0 tabular-nums">{{ number_format($bucket['count']) }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Freshness window: {{ $dashboard['freshness_start'] }} through {{ $dashboard['freshness_end'] }} inclusive. Latest stored check date: {{ $dashboard['offers']['latest_check_date'] ?? 'none' }}. Latest record edit: {{ $dashboard['offers']['latest_record_edit'] ?? 'none' }}.</p>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="offers-by-competitor-heading" class="space-y-3">
            <h2 id="offers-by-competitor-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Offers by competitor</h2>
            <x-filament::section>
                @if (! $dashboard['offers']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Unavailable without competitor-offer list permission.</p>
                @elseif (! $dashboard['competitors']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Per-competitor coverage requires competitor-list permission.</p>
                @elseif (count($dashboard['offers']['by_competitor'] ?? []) === 0)
                    <p class="text-sm text-gray-600 dark:text-gray-300">No current competitor records are available.</p>
                @else
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">{{ number_format($dashboard['offers']['competitors_without_offers_count']) }} current competitors have no current offers. Showing up to 10 by offer count.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400"><tr><th scope="col" class="py-3 pr-4">Competitor</th><th scope="col" class="px-3 py-3 text-right">Current offers</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($dashboard['offers']['by_competitor'] as $competitor)
                                    <tr><th scope="row" class="py-3 pr-4 font-medium">{{ $competitor['name'] }}</th><td class="px-3 py-3 text-right tabular-nums">{{ number_format($competitor['offer_count']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="recent-offer-checks-heading" class="space-y-3">
            <h2 id="recent-offer-checks-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Recent offer checks</h2>
            <x-filament::section>
                @if (! $dashboard['offers']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Unavailable without competitor-offer list permission.</p>
                @elseif (count($dashboard['offers']['recent_checks']) === 0)
                    <p class="text-sm text-gray-600 dark:text-gray-300">No current offers have a check date in the freshness window.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($dashboard['offers']['recent_checks'] as $offer)
                            <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between">
                                <div><p class="font-medium">{{ $offer['title'] }}</p><p class="text-sm text-gray-600 dark:text-gray-300">{{ $offer['competitor_name'] ?? 'Competitor details unavailable' }} · Stored status: {{ $offer['status'] }}</p>@if ($offer['competitor_site'])<p class="break-all text-xs text-gray-500 dark:text-gray-400">Competitor website field: {{ $offer['competitor_site'] }}</p>@endif</div>
                                <time class="shrink-0 text-sm text-gray-600 dark:text-gray-300">Checked {{ $offer['last_check'] }}</time>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="comparison-notes-heading" class="space-y-3">
            <h2 id="comparison-notes-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Recorded qualitative comparisons</h2>
            <x-filament::section>
                @if (! $dashboard['offers']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Unavailable without competitor-offer list permission.</p>
                @elseif (count($dashboard['offers']['qualitative_comparisons']) === 0)
                    <p class="text-sm text-gray-600 dark:text-gray-300">No current offers have both comparison-note fields populated.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($dashboard['offers']['qualitative_comparisons'] as $offer)
                            <li class="space-y-2 py-4">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between"><div><p class="font-semibold">{{ $offer['title'] }}</p><p class="text-sm text-gray-600 dark:text-gray-300">{{ $offer['competitor_name'] ?? 'Competitor details unavailable' }}</p>@if ($offer['competitor_site'])<p class="break-all text-xs text-gray-500 dark:text-gray-400">Competitor website field: {{ $offer['competitor_site'] }}</p>@endif</div><p class="text-xs text-gray-500 dark:text-gray-400">Last check: {{ $offer['last_check'] ?? 'not recorded' }}</p></div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Our similar offer (entered note)</p><p class="mt-1 whitespace-pre-line text-sm">{{ $offer['our_similar_offer'] }}</p></div>
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Their differentiator (entered note)</p><p class="mt-1 whitespace-pre-line text-sm">{{ $offer['their_differentiator'] }}</p></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if ($dashboard['offers']['with_both_notes_count'] > count($dashboard['offers']['qualitative_comparisons']))
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Showing the five most recently checked offers with both notes recorded.</p>
                    @endif
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="offer-observation-history-heading" class="space-y-3">
            <h2 id="offer-observation-history-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Historical competitor-offer observations</h2>
            <x-filament::section>
                @if (! $dashboard['offers']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Unavailable without competitor-offer list permission.</p>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Recorded snapshots</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['observation_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Snapshots with source URL</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['observations_with_source_count']) }}</p></div>
                        <div><p class="text-sm text-gray-600 dark:text-gray-300">Unknown source</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['offers']['observations_with_unknown_source_count']) }}</p></div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Latest observation time: {{ $dashboard['offers']['latest_observation_at'] ?? 'none' }}. Source presence does not independently verify source accuracy.</p>
                    @if (count($dashboard['offers']['recent_observations']) === 0)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">No observation snapshots have been recorded yet. Existing records are not backfilled as fabricated historical observations.</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400"><tr><th scope="col" class="py-3 pr-4">Offer</th><th scope="col" class="px-3 py-3">Price</th><th scope="col" class="px-3 py-3">Source</th><th scope="col" class="px-3 py-3">Observed</th><th scope="col" class="px-3 py-3">Recorded</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($dashboard['offers']['recent_observations'] as $observation)
                                        <tr>
                                            <th scope="row" class="py-3 pr-4 font-medium">@if ($observation['history_url'])<a class="underline" href="{{ $observation['history_url'] }}">{{ $observation['title'] }}</a>@else{{ $observation['title'] }}@endif<p class="text-xs font-normal text-gray-500">
                                                @if ($observation['history_url'])
                                                    Open offer and observation history
                                                @endif
                                            </p></th>
                                            <td class="px-3 py-3">{{ $observation['offer_price'] === null ? 'Unknown price' : number_format((float) $observation['offer_price'], 2).' '.($observation['currency'] ?? 'Unknown currency') }}<span class="block text-xs text-gray-500">{{ $observation['price_basis'] ?? 'Unknown basis' }}</span></td>
                                            <td class="px-3 py-3">@if ($observation['source_url'])<a class="break-all underline" href="{{ $observation['source_url'] }}" target="_blank" rel="noopener noreferrer">Source page</a>@else<span class="text-gray-500">Unknown</span>@endif</td>
                                            <td class="px-3 py-3">{{ $observation['observed_at'] ?? 'Unknown' }}</td>
                                            <td class="px-3 py-3">{{ $observation['recorded_at'] ?? 'Unknown' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="google-ads-heading" class="space-y-3">
            <h2 id="google-ads-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Google Ads campaign performance</h2>
            <x-filament::section>
                @if (! $dashboard['google_ads']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Unavailable without paid-campaign list permission.</p>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-300">Imported daily metrics for {{ $dashboard['google_ads']['from'] }} through {{ $dashboard['google_ads']['to'] }}. Latest import: {{ $dashboard['google_ads']['latest_imported_at'] ?? 'none' }}. This section reads stored imports and does not call Google Ads live.</p>
                    @if ($dashboard['google_ads']['row_count'] === 0)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">No imported campaign metrics are available for this period. Configure credentials and run the documented dry-run/import command.</p>
                    @else
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div><p class="text-sm text-gray-600 dark:text-gray-300">Campaign-day rows</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['google_ads']['row_count']) }}</p></div>
                            <div><p class="text-sm text-gray-600 dark:text-gray-300">Campaigns represented</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($dashboard['google_ads']['campaign_count']) }}</p></div>
                            <div><p class="text-sm text-gray-600 dark:text-gray-300">Currency totals</p><p class="mt-1 text-sm">{{ implode(', ', array_map(fn ($total) => $total['currency_code'], $dashboard['google_ads']['currency_totals'])) }}</p></div>
                        </div>
                        <h3 class="mt-4 font-medium">Totals by account currency</h3>
                        <div class="mt-2 space-y-2">
                            @foreach ($dashboard['google_ads']['currency_totals'] as $total)
                                <div class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700 sm:grid-cols-5">
                                    <div><p class="text-gray-500">Currency</p><p class="font-semibold">{{ $total['currency_code'] }}</p></div>
                                    <div><p class="text-gray-500">Cost</p><p class="font-semibold tabular-nums">{{ number_format(((float) $total['cost_micros']) / 1000000, 2) }} {{ $total['currency_code'] }}</p></div>
                                    <div><p class="text-gray-500">Impressions</p><p class="font-semibold tabular-nums">{{ number_format($total['impressions']) }}</p></div>
                                    <div><p class="text-gray-500">Clicks</p><p class="font-semibold tabular-nums">{{ number_format($total['clicks']) }}</p></div>
                                    <div><p class="text-gray-500">Reported conversions</p><p class="font-semibold tabular-nums">{{ number_format((float) $total['conversions'], 2) }}</p></div>
                                </div>
                            @endforeach
                        </div>
                        <h3 class="mt-4 font-medium">Campaign detail, grouped by currency</h3>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400"><tr><th scope="col" class="py-3 pr-4">Campaign</th><th scope="col" class="px-3 py-3">Currency</th><th scope="col" class="px-3 py-3 text-right">Cost</th><th scope="col" class="px-3 py-3 text-right">Clicks</th><th scope="col" class="px-3 py-3 text-right">Impressions</th><th scope="col" class="px-3 py-3 text-right">Reported conversions</th><th scope="col" class="px-3 py-3 text-right">Cost / conversion</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($dashboard['google_ads']['campaign_rows'] as $campaign)
                                        <tr><th scope="row" class="py-3 pr-4 font-medium">{{ $campaign['campaign_name'] }}</th><td class="px-3 py-3">{{ $campaign['currency_code'] }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format(((float) $campaign['cost_micros']) / 1000000, 2) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($campaign['clicks']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($campaign['impressions']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format((float) $campaign['conversions'], 2) }}</td><td class="px-3 py-3 text-right tabular-nums">@if ((float) $campaign['conversions'] > 0){{ number_format(((float) $campaign['cost_micros']) / 1000000 / (float) $campaign['conversions'], 2) }} {{ $campaign['currency_code'] }}@else<span class="text-gray-500">Unavailable</span>@endif</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Provider-reported conversions and cost per reported conversion are not unique acquired customers, CAC, revenue, or profit. Currency totals are kept separate. Access requires <code>paid_campaign.index</code>.</p>
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="market-trends-heading" class="space-y-3">
            <h2 id="market-trends-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Market trends and demand signals</h2>
            <x-filament::section>
                @if (! $dashboard['market_trends']['can_view'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">Unavailable without market-trend list permission.</p>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ number_format($dashboard['market_trends']['count']) }} active observations match the selected filters. Latest observation: {{ $dashboard['market_trends']['latest_observation_at'] ?? 'none' }}. Observations are source-reported measurements, not forecasts or proof of market-wide conditions.</p>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <label class="text-sm">Period start<input type="date" wire:model.live="periodStart" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                        <label class="text-sm">Period end<input type="date" wire:model.live="periodEnd" min="{{ $dashboard['market_trends']['filters']['period_start'] ?? '' }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                        <label class="text-sm">Market<select wire:model.live="marketFilter" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950"><option value="">All markets</option>@foreach ($dashboard['market_trends']['filter_options']['markets'] as $market)<option value="{{ $market }}">{{ $market }}</option>@endforeach</select></label>
                        <label class="text-sm">Segment<select wire:model.live="segmentFilter" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950"><option value="">All segments</option>@foreach ($dashboard['market_trends']['filter_options']['segments'] as $segment)<option value="{{ $segment }}">{{ $segment }}</option>@endforeach</select></label>
                        <label class="text-sm">Metric<select wire:model.live="metricFilter" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950"><option value="">All metrics</option>@foreach ($dashboard['market_trends']['filter_options']['metrics'] as $metric)<option value="{{ $metric }}">{{ $metric }}</option>@endforeach</select></label>
                    </div>
                    <div class="mt-2 flex justify-end"><x-filament::button color="gray" size="sm" wire:click="resetFilters">Clear filters</x-filament::button></div>
                    @if (count($dashboard['market_trends']['recent']) === 0)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">No active observations match these filters. Clear filters or widen the period to see available records.</p>
                    @else
                        <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400"><tr><th scope="col" class="py-3 pr-4">Observation</th><th scope="col" class="px-3 py-3">Value</th><th scope="col" class="px-3 py-3">Market / segment</th><th scope="col" class="px-3 py-3">Period</th><th scope="col" class="px-3 py-3">Source and quality</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($dashboard['market_trends']['recent'] as $trend)
                                <tr><th scope="row" class="py-3 pr-4 font-medium">@if ($trend['detail_url'])<a class="underline" href="{{ $trend['detail_url'] }}">{{ $trend['name'] }}</a>@else{{ $trend['name'] }}@endif<p class="text-xs font-normal text-gray-500">{{ $trend['metric_key'] }}@if ($trend['change_pct'] !== null) · {{ $trend['change_pct'] }}% reported change @endif</p></th><td class="px-3 py-3 tabular-nums">{{ $trend['data_status'] === 'missing' ? 'Missing' : $trend['value'] }} {{ $trend['data_status'] === 'missing' ? '' : $trend['unit'] }}<p class="text-xs text-gray-500">Confidence: {{ $trend['confidence'] === null ? 'unknown' : number_format((float) $trend['confidence'] * 100, 1).'%' }}</p></td><td class="px-3 py-3">{{ $trend['market'] }} / {{ $trend['segment'] ?? 'unspecified' }}</td><td class="px-3 py-3">{{ $trend['period_start'] }} – {{ $trend['period_end'] }}</td><td class="px-3 py-3">@if ($trend['source_url'])<a class="underline" href="{{ $trend['source_url'] }}" target="_blank" rel="noopener noreferrer">{{ $trend['source_name'] ?: 'Source' }}</a>@else{{ $trend['source_name'] ?: 'Unknown source' }}@endif<p class="text-xs text-gray-500">Observed {{ $trend['observed_at'] ?? 'time unknown' }} · {{ $trend['source_type'] }} · {{ $trend['freshness_status'] }} · {{ $trend['verification_status'] }} · {{ $trend['data_status'] }}@if ($trend['missing_reason']) · {{ $trend['missing_reason'] }}@endif</p></td></tr>
                            @endforeach
                        </tbody></table></div>
                        @if ($dashboard['market_trends']['count'] > count($dashboard['market_trends']['recent']))
                            <p class="mt-2 text-xs text-gray-500">Showing the 50 most recently observed matching records.</p>
                        @endif
                    @endif
                @endif
            </x-filament::section>
        </section>

        <section aria-labelledby="market-data-notes-heading" class="space-y-3">
            <h2 id="market-data-notes-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Data notes</h2>
            <x-filament::section>
                <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                    <li>Only current records contribute to inventory, freshness, and comparison counts; soft-deleted records are reported separately.</li>
                    <li>Last checks dated {{ $dashboard['freshness_start'] }} through {{ $dashboard['freshness_end'] }} are recent; earlier checks are stale; dates after today are future-dated.</li>
                    <li>New offer snapshots can retain an offer-level source URL, currency, price basis, validity window, and separate observed/recorded times. Legacy values remain unknown until verified.</li>
                    <li>Prices are not averaged or compared across currencies or price bases. A source URL is provenance metadata, not independent proof of accuracy.</li>
                    <li>Profile fields are completeness counts only; populated social or website fields are not checked for validity. The app has no live market-data integration.</li>
                    <li>Competitor inventory requires <code>competitor.index</code>; offer metrics require <code>competitor_offer.index</code>; per-competitor comparisons require both. Create links require the matching <code>*.create</code> permission. The <code>market-analysis.view</code> page gate does not grant resource access.</li>
                </ul>
            </x-filament::section>
        </section>
    </div>
</x-filament-panels::page>
