<x-filament-panels::page>
    @php($summary = $this->getDashboardSummary())
    @php($urls = $this->getAuthorizedResourceUrls())

    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">Product catalog and commercial readiness as of {{ $summary['as_of'] }}. Prices use the consolidated product_prices ledger and only display when exactly one price is effective today. Product and price statuses remain distinct; availability is shown only where the linked model has a date range.</p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['label' => 'Products', 'value' => $summary['total']], ['label' => 'With one effective price', 'value' => $summary['with_effective_price']], ['label' => 'Missing effective price', 'value' => $summary['missing_price']], ['label' => 'Ambiguous effective price', 'value' => $summary['ambiguous_price']]] as $metric)
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($metric['value']) }}</p>
                </x-filament::section>
            @endforeach
        </div>

        @if ($summary['missing_productable'] > 0)
            <x-filament::section>
                <p class="text-sm">{{ number_format($summary['missing_productable']) }} product(s) link to a missing or unsupported productable record. Their price and availability are not inferred.</p>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Catalog</x-slot>
            <x-slot name="description">Filter by stored product status, supported productable type, and price readiness. The stored status is displayed as-is because the schema does not define a controlled lifecycle vocabulary.</x-slot>

            <div class="grid gap-4 md:grid-cols-3">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="statusFilter">
                        <option value="">All statuses</option>
                        @foreach ($summary['statuses'] as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="typeFilter">
                        <option value="">All product types</option>
                        @foreach ($summary['types'] as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="priceFilter">
                        <option value="">All price states</option>
                        <option value="available">One effective price</option>
                        <option value="missing">Missing effective price</option>
                        <option value="ambiguous">Ambiguous effective price</option>
                        <option value="unavailable product record">Missing productable record</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="text-left text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-3">Product</th>
                            <th class="px-3 py-3">Type / linked record</th>
                            <th class="px-3 py-3">Stored status</th>
                            <th class="px-3 py-3">Effective price</th>
                            <th class="px-3 py-3">Availability</th>
                            <th class="px-3 py-3">Price validity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($summary['products'] as $product)
                            <tr>
                                <td class="px-3 py-3 font-medium text-gray-950 dark:text-white">@if ($this->getProductUrl($product['id']))<x-filament::link :href="$this->getProductUrl($product['id'])">{{ $product['title'] }}</x-filament::link>@else{{ $product['title'] }}@endif <span class="block text-xs font-normal text-gray-500">#{{ $product['id'] }}</span></td>
                                <td class="px-3 py-3">{{ $product['type_label'] }}<span class="block text-xs text-gray-500">@if ($product['productable_exists'] && $this->getProductableUrl($product['type'], $product['productable_id']))<x-filament::link :href="$this->getProductableUrl($product['type'], $product['productable_id'])">{{ $product['productable_title'] ?? 'Title unavailable' }}</x-filament::link>@else{{ $product['productable_title'] ?? ($product['productable_exists'] ? 'Title unavailable' : 'Linked record missing') }}@endif</span></td>
                                <td class="px-3 py-3">{{ $product['status'] !== '' ? $product['status'] : 'Unspecified' }}</td>
                                <td class="px-3 py-3">
                                    @if ($product['price_state'] === 'available')
                                        {{ number_format((float) $product['price'], 2) }} {{ $product['currency'] ?: 'Currency unspecified' }}
                                    @elseif ($product['price_state'] === 'ambiguous')
                                        Ambiguous ({{ $product['price_state'] }})
                                    @else
                                        {{ $product['price_state'] === 'missing' ? 'No effective price' : 'Unavailable' }}
                                    @endif
                                </td>
                                <td class="px-3 py-3">{{ $product['availability'] }}</td>
                                <td class="px-3 py-3">{{ $product['valid_from'] ?? 'No start bound' }} – {{ $product['valid_until'] ?? 'No end bound' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">No products match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Authorized resource lists</x-slot>
            <div class="flex flex-wrap gap-4">
                @foreach (['products' => 'Products', 'prices' => 'Product prices', 'camps' => 'Camps', 'courses' => 'Courses', 'events' => 'Events', 'workshops' => 'Workshops'] as $key => $label)
                    @if ($urls[$key])
                        <x-filament::link :href="$urls[$key]">{{ $label }}</x-filament::link>
                    @endif
                @endforeach
            </div>
        </x-filament::section>

        <p class="text-xs text-gray-500">Definitions: only non-deleted products and prices are counted. An effective price has validity bounds that include today. More than one effective price for a linked record is ambiguous, so no amount is selected. Supported productable types are Camp, Course, Event, and Workshop. Availability uses start_on/end_on for camps and start_date/end_date for events and workshops; Course has no availability dates. Product status is not treated as availability.</p>
    </div>
</x-filament-panels::page>