<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-950 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
            <strong>Provisional operational reporting only.</strong>
            These figures are not approved profitability, recognized revenue, statutory accounting profit, or a reconciled financial statement. Revenue and cost source eligibility, historical mapping, and finance-owner validation remain open. Missing, unknown, excluded, or unallocated inputs are not treated as zero. Currency totals are always kept separate.
        </div>

        @if ($canCalculate)
            <form wire:submit="calculate" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Create a calculation run</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Each run stores its period, formula version, assumptions, source IDs, and exclusions. Corrections create a new immutable run.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <label class="text-sm font-medium">Period start
                        <input type="date" wire:model="periodStart" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <label class="text-sm font-medium">Period end
                        <input type="date" wire:model="periodEnd" required min="{{ $periodStart }}" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <div class="flex items-end">
                        <x-filament::button type="submit" wire:loading.attr="disabled">Create provisional run</x-filament::button>
                    </div>
                </div>
            </form>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2.5fr)]">
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="font-semibold">Calculation history</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($runs as $run)
                        <button type="button" wire:click="selectRun({{ $run->id }})" class="w-full rounded-lg border p-3 text-left {{ ($selectedRun?->id === $run->id) ? 'border-primary-500 bg-primary-50 dark:bg-primary-950' : 'border-gray-200 dark:border-gray-700' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium">{{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}</span>
                                <span class="text-xs uppercase tracking-wide text-warning-700">{{ $run->status }}</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">{{ $run->formula_version }} · {{ $run->created_at?->format('d M Y H:i') }}</div>
                        </button>
                    @empty
                        <p class="text-sm text-gray-500">No calculation runs yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="space-y-4">
                @if ($selectedRun && $report)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-semibold">Run {{ $selectedRun->run_id }}</h2>
                            <span class="rounded-full bg-warning-100 px-3 py-1 text-xs font-semibold text-warning-800">PROVISIONAL</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $selectedRun->period_start->format('d M Y') }} to {{ $selectedRun->period_end->format('d M Y') }} · {{ $selectedRun->formula_version }}</p>
                        <h3 class="mt-4 text-sm font-semibold">Metric definitions and assumptions</h3>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                            @foreach ($selectedRun->assumptions as $key => $value)
                                @if (is_string($value))
                                    <li><strong>{{ str($key)->replace('_', ' ')->title() }}:</strong> {{ $value }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">Report filters</h3>
                                <p class="mt-1 text-xs text-gray-500">Filters apply to this immutable calculation run.</p>
                            </div>
                            <x-filament::button color="gray" size="sm" wire:click="resetFilters">Clear filters</x-filament::button>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="text-sm font-medium">Product
                                <select wire:model.live="productFilter" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                    <option value="">All products</option>
                                    @foreach ($report['available_products'] as $product)
                                        <option value="{{ $product['id'] }}">{{ $product['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-sm font-medium">Currency
                                <select wire:model.live="currencyFilter" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                    <option value="">All currencies (separate totals)</option>
                                    @foreach ($report['available_currencies'] as $currency)
                                        <option value="{{ $currency }}">{{ $currency }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    <section class="space-y-3">
                        <h3 class="font-semibold">Period totals by currency</h3>
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @forelse ($report['currency_totals'] as $currency => $totals)
                                <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-semibold">{{ $currency }}</h4>
                                        <span class="text-xs {{ $totals['complete'] ? 'text-success-600' : 'text-warning-600' }}">{{ $totals['complete'] ? 'Complete within v1 inputs' : 'Incomplete' }}</span>
                                    </div>
                                    <dl class="mt-3 space-y-2 text-sm">
                                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Revenue</dt><dd class="font-medium">{{ $totals['revenue_amount'] ?? 'Unknown' }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Direct cost</dt><dd class="font-medium">{{ $totals['direct_cost_amount'] ?? 'Unknown' }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Allocated overhead</dt><dd class="font-medium">{{ $totals['overhead_cost_amount'] ?? 'Unknown' }}</dd></div>
                                        <div class="flex justify-between gap-3 border-t border-gray-100 pt-2 dark:border-gray-700"><dt class="font-semibold">Provisional contribution</dt><dd class="font-semibold">{{ $totals['provisional_profit_amount'] ?? 'Withheld' }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Contribution margin</dt><dd>{{ $totals['provisional_margin_percent'] === null ? 'Withheld' : $totals['provisional_margin_percent'].'%' }}</dd></div>
                                    </dl>
                                </article>
                            @empty
                                <p class="text-sm text-gray-500">No report rows match the selected filters.</p>
                            @endforelse
                        </div>
                    </section>

                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                        <div class="border-b border-gray-100 p-4 dark:border-gray-800">
                            <h3 class="font-semibold">Product report and source references</h3>
                            <p class="mt-1 text-xs text-gray-500">Amounts use only the canonical inputs stored by the calculation. Unknown or incomplete components remain blank.</p>
                        </div>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                                <tr><th class="p-3">Product</th><th class="p-3">Currency</th><th class="p-3">Revenue</th><th class="p-3">Direct</th><th class="p-3">Overhead</th><th class="p-3">Contribution</th><th class="p-3">Margin</th><th class="p-3">Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($report['results'] as $result)
                                    <tr class="border-t border-gray-100 align-top dark:border-gray-800">
                                        <td class="p-3">
                                            @php($productUrl = $this->productUrl((int) $result->product_id))
                                            @if ($productUrl)
                                                <a href="{{ $productUrl }}" class="font-medium text-primary-600 hover:underline">{{ $result->product?->title ?? 'Deleted / unavailable product' }}</a>
                                            @else
                                                <span class="font-medium">{{ $result->product?->title ?? 'Deleted / unavailable product' }}</span>
                                            @endif
                                            <div class="mt-2 text-xs text-gray-500">Revenue rows:
                                                @forelse (($result->source_snapshot['revenue_ids'] ?? []) as $sourceId)
                                                    @php($revenueUrl = $this->productRevenueUrl((int) $sourceId))
                                                    @if ($revenueUrl)<a href="{{ $revenueUrl }}" class="text-primary-600 hover:underline">#{{ $sourceId }}</a>@else #{{ $sourceId }}@endif{{ !$loop->last ? ', ' : '' }}
                                                @empty none @endforelse
                                            </div>
                                            <div class="text-xs text-gray-500">Allocation snapshots: {{ implode(', ', $result->source_snapshot['cost_snapshot_ids'] ?? []) ?: 'none' }}</div>
                                            <div class="text-xs text-gray-500">Source resources: {{ implode(', ', $result->source_snapshot['cost_resource_ids'] ?? []) ?: 'none' }}</div>
                                            <div class="text-xs text-gray-500">Rule versions: {{ collect($result->source_snapshot['cost_rule_versions'] ?? [])->map(fn ($rule) => 'resource #'.$rule['resource_id'].' / rule #'.$rule['allocation_id'].' / v'.$rule['rule_version'])->implode('; ') ?: 'none' }}</div>
                                        </td>
                                        <td class="p-3">{{ $result->currency_code }}</td>
                                        <td class="p-3">{{ $result->revenue_amount ?? 'Unknown' }}</td>
                                        <td class="p-3">{{ $result->direct_cost_amount ?? 'Unknown' }}</td>
                                        <td class="p-3">{{ $result->overhead_cost_amount ?? 'Unknown' }}</td>
                                        <td class="p-3">{{ $result->provisional_profit_amount ?? 'Withheld' }}</td>
                                        <td class="p-3">{{ $result->provisional_margin_percent === null ? 'Withheld' : $result->provisional_margin_percent.'%' }}</td>
                                        <td class="p-3">{{ $result->result_status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="p-4 text-gray-500">No products match the selected filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($canViewCustomerDetails)
                        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                            <div>
                                <h3 class="font-semibold">Customer revenue drill-down</h3>
                                <p class="mt-1 text-xs text-gray-500">Customer attribution follows only ProductRevenue → CustomerInvoice → Customer. Invoice status and invoice totals are not used as revenue. Customer-level costs and profit are unavailable because cost allocations do not identify customers.</p>
                            </div>
                            @if (count($report['customer_reconciliation_issues']) > 0)
                                <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
                                    Some revenue source rows no longer reconcile to the immutable run. Customer amounts for those product/currency rows are withheld.
                                    <ul class="mt-2 list-disc pl-5">
                                        @foreach ($report['customer_reconciliation_issues'] as $issue)
                                            <li>{{ $issue['reason'] }} @if(isset($issue['source_id'])) Source #{{ $issue['source_id'] }} @endif</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-left dark:bg-gray-800"><tr><th class="p-3">Customer</th><th class="p-3">Currency</th><th class="p-3">Revenue</th><th class="p-3">Direct cost</th><th class="p-3">Overhead</th><th class="p-3">Profit</th><th class="p-3">Source invoices</th></tr></thead>
                                    <tbody>
                                        @forelse ($report['customers'] as $customer)
                                            <tr class="border-t border-gray-100 align-top dark:border-gray-800">
                                                <td class="p-3">
                                                    @php($customerUrl = $this->customerUrl($customer['customer_id']))
                                                    @if ($customerUrl)<a href="{{ $customerUrl }}" class="font-medium text-primary-600 hover:underline">{{ $customer['customer_name'] }}</a>@else {{ $customer['customer_name'] }}@endif
                                                </td>
                                                <td class="p-3">{{ $customer['currency'] }}</td>
                                                <td class="p-3">{{ $customer['revenue_amount'] }}</td>
                                                <td class="p-3">Not attributed</td>
                                                <td class="p-3">Not attributed</td>
                                                <td class="p-3">Withheld</td>
                                                <td class="p-3">
                                                    @forelse ($customer['invoice_ids'] as $invoiceId)
                                                        @php($invoiceUrl = $this->customerInvoiceUrl($invoiceId))
                                                        @if ($invoiceUrl)<a href="{{ $invoiceUrl }}" class="text-primary-600 hover:underline">#{{ $invoiceId }}</a>@else #{{ $invoiceId }}@endif{{ !$loop->last ? ', ' : '' }}
                                                    @empty none @endforelse
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="p-3 text-gray-500">No reconciled customer-attributed revenue is available for this selection.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if (count($report['unattributed']) > 0)
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">
                                    <h4 class="font-semibold">Revenue without customer attribution</h4>
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($report['unattributed'] as $item)
                                            <li>{{ $item['revenue_amount'] }} {{ $item['currency'] }} · revenue rows {{ implode(', ', $item['revenue_ids']) }} · {{ $item['reason'] }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </section>
                    @else
                        <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">Customer drill-down requires both the profitability customer-view permission and access to customer invoices.</div>
                    @endif

                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <h3 class="font-semibold">Unavailable dimensions and exclusions</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300"><strong>Project:</strong> {{ $report['project_dimension']['reason'] }}</p>
                        @if (count($report['exceptions']) > 0)
                            <ul class="mt-3 space-y-2 text-sm">
                                @foreach ($report['exceptions'] as $exception)
                                    <li class="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                                        <strong>{{ str($exception['type'] ?? 'exception')->replace('_', ' ')->title() }}</strong>
                                        · {{ $exception['reason'] ?? ('Source record #'.($exception['source_id'] ?? $exception['snapshot_id'] ?? 'unknown')) }}
                                        @if (isset($exception['amount'])) · {{ $exception['amount'] }} {{ $exception['currency'] ?? '' }} @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-gray-500">No excluded or unallocated records were recorded for this run and filter selection.</p>
                        @endif
                        <p class="mt-3 text-xs text-gray-500">Run source IDs and allocation rule versions are retained for audit. Customer-level costs are not inferred from product-level allocations.</p>
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">Select a calculation run or create the first one.</div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
