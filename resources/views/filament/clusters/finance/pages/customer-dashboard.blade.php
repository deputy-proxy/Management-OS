<x-filament-panels::page>
    @php($summary = $this->getDashboardSummary())
    @php($urls = $this->getAuthorizedResourceUrls())

    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">Customer and lead operations as of {{ $summary['as_of'] }}. Converted leads are tracked through their customer record and are excluded from lead counts to avoid double-counting.</p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['label' => 'Customers', 'value' => $summary['customers'], 'url' => $urls['customers']], ['label' => 'Unconverted leads', 'value' => $summary['open_leads'], 'url' => $urls['leads']], ['label' => 'Active contracts', 'value' => $summary['active_contracts'], 'url' => $urls['contracts']], ['label' => 'Interactions, last 30 days', 'value' => $summary['interactions_last_30_days'], 'url' => $urls['interactions']]] as $metric)
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($metric['value']) }}</p>
                    @if ($metric['url'])
                        <x-filament::link class="mt-3" :href="$metric['url']">View records</x-filament::link>
                    @else
                        <p class="mt-3 text-xs text-gray-500">Record list restricted</p>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Unconverted lead status</x-slot>
                <x-slot name="description">Counts by stored status, excluding leads already linked to a converted customer.</x-slot>
                @forelse ($summary['lead_statuses'] as $status)
                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span>{{ $status['status'] !== '' ? $status['status'] : 'Unspecified' }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($status['count']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No unconverted leads.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Customer invoice status</x-slot>
                <x-slot name="description">Invoice counts for the current calendar month, grouped by stored status.</x-slot>
                @forelse ($summary['invoice_statuses'] as $status)
                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span>{{ $status['status'] !== '' ? $status['status'] : 'Unspecified' }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($status['count']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No customer invoices for this month.</p>
                @endforelse
                @if ($urls['invoices'])
                    <x-filament::link class="mt-3" :href="$urls['invoices']">View invoices</x-filament::link>
                @endif
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Recent customer interactions</x-slot>
                <x-slot name="description">Interactions recorded in the last 30 days, newest first ({{ $summary['recent_since'] }} onward).</x-slot>
                @forelse ($summary['recent_interactions'] as $interaction)
                    <div class="border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                        <div class="flex flex-wrap justify-between gap-2">
                            <p class="font-medium text-gray-950 dark:text-white">{{ $interaction->customer?->name ?? 'Customer unavailable' }}</p>
                            <time class="text-xs text-gray-500">{{ $interaction->timestamp }}</time>
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $interaction->reason }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No interactions recorded in this period.</p>
                @endforelse
                @if ($urls['interactions'])
                    <x-filament::link class="mt-3" :href="$urls['interactions']">View interactions</x-filament::link>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Recent unconverted leads</x-slot>
                <x-slot name="description">The eight most recently created leads that have not been converted.</x-slot>
                @forelse ($summary['recent_leads'] as $lead)
                    <div class="border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                        <p class="font-medium text-gray-950 dark:text-white">{{ $lead->full_name }}</p>
                        <div class="mt-1 flex flex-wrap justify-between gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <span>{{ $lead->status ?: 'Unspecified status' }}</span>
                            <span>{{ $lead->email }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Created {{ $lead->created_at?->format('M j, Y') ?? 'date unavailable' }} · Last contacted {{ $lead->last_contacted_at?->format('M j, Y') ?? 'not recorded' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No unconverted leads.</p>
                @endforelse
                @if ($urls['leads'])
                    <x-filament::link class="mt-3" :href="$urls['leads']">View leads</x-filament::link>
                @endif
            </x-filament::section>
        </div>

        <p class="text-xs text-gray-500">Definitions: customers and leads exclude soft-deleted rows; converted leads are excluded from lead metrics; active contracts have a start date on or before today and an end date on or after today; interaction activity uses the interaction timestamp; invoice periods use the stored billing month. Invoice and lead statuses are displayed as stored because the schema does not define a controlled status vocabulary.</p>
    </div>
</x-filament-panels::page>
