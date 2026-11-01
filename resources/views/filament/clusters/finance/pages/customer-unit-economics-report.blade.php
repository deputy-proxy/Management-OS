<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-950 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
            <strong>Operational unit-economics preview only.</strong>
            CAC and LTV are withheld unless the customer-level attribution, deduplication, cost basis, revenue basis, and lifetime-window policy switches are explicitly approved and configured. The shipped defaults are unapproved. When enabled, CAC uses evidence-backed cost entries and LTV uses signed canonical ProductRevenue rows linked through customer invoices. These values are not automatically finance-approved, recognized revenue, collected cash, gross margin, or profit. Shared costs, unknown attribution, missing links, and incomplete revenue coverage remain visible and are never silently allocated or treated as zero.
        </div>

        @if ($canManageCosts)
            <form wire:submit="recordCost" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="font-semibold">Record acquisition cost evidence</h2>
                <p class="mt-1 text-sm text-gray-500">Use one explicit source category and currency per entry. Leave the category blank for shared/unattributed costs, which will be excluded from CAC until allocated with evidence. Entries are append-only.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <label class="text-sm font-medium">Incurred on
                        <input type="date" wire:model="costIncurredOn" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <label class="text-sm font-medium">Source category
                        <select wire:model="costSourceCategory" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Shared / unattributed</option>
                            @foreach ($sourceCategories as $value => $label)
                                @if ($value !== 'unknown')
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium">Campaign (optional)
                        <input type="text" wire:model="costCampaignName" maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <label class="text-sm font-medium">Amount
                        <input type="number" min="0.01" step="0.01" wire:model="costAmount" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <label class="text-sm font-medium">Currency
                        <input type="text" minlength="3" maxlength="3" wire:model="costCurrency" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <label class="text-sm font-medium">Source reference
                        <input type="text" wire:model="costSourceReference" maxlength="512" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <label class="text-sm font-medium md:col-span-2">Evidence reference
                        <input type="text" wire:model="costEvidenceReference" maxlength="512" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <div class="flex items-end">
                        <x-filament::button type="submit" wire:loading.attr="disabled">Record cost evidence</x-filament::button>
                    </div>
                </div>
            </form>
        @endif

        @if ($canCalculate)
            <form wire:submit="calculate" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="font-semibold">Create an immutable calculation run</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <label class="text-sm font-medium">Acquisition period start
                        <input type="date" wire:model="periodStart" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <label class="text-sm font-medium">Acquisition period end
                        <input type="date" wire:model="periodEnd" required min="{{ $periodStart }}" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                    <div class="flex items-end">
                        <x-filament::button type="submit" wire:loading.attr="disabled">Calculate CAC &amp; LTV</x-filament::button>
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
                            <div class="font-medium">{{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $run->policy_version }} · {{ $run->created_at?->format('d M Y H:i') }}</div>
                        </button>
                    @empty
                        <p class="text-sm text-gray-500">No CAC/LTV calculation runs yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="space-y-4">
                @if ($selectedRun)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <h2 class="font-semibold">Run {{ $selectedRun->run_id }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $selectedRun->period_start->format('d M Y') }} to {{ $selectedRun->period_end->format('d M Y') }} · {{ $selectedRun->policy_version }}</p>
                        <p class="mt-3 text-xs text-gray-500">{{ $selectedRun->policy_snapshot['revenue_semantics'] ?? '' }} {{ $selectedRun->policy_snapshot['shared_cost_treatment'] ?? '' }}</p>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                                <tr>
                                    <th class="p-3">First-touch source</th><th class="p-3">Currency</th><th class="p-3">Acquired customers</th><th class="p-3">Acquisition cost</th><th class="p-3">CAC</th><th class="p-3">Operational revenue</th><th class="p-3">LTV</th><th class="p-3">Revenue coverage</th><th class="p-3">Status</th><th class="p-3">Source trace</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($selectedRun->results as $result)
                                    <tr class="border-t border-gray-100 align-top dark:border-gray-800">
                                        <td class="p-3">{{ $sourceCategories[$result->source_category] ?? $result->source_category }}</td>
                                        <td class="p-3">{{ $result->currency_code }}</td>
                                        <td class="p-3">{{ $result->acquired_customer_count }}</td>
                                        <td class="p-3">{{ $result->acquisition_cost_amount ?? 'Unknown' }}</td>
                                        <td class="p-3">{{ $result->cac_amount ?? 'Withheld' }}</td>
                                        <td class="p-3">{{ $result->revenue_amount ?? 'Unknown' }}</td>
                                        <td class="p-3">{{ $result->ltv_amount ?? 'Withheld' }}</td>
                                        <td class="p-3">{{ $result->revenue_coverage_count }} / {{ $result->acquired_customer_count }} ({{ $result->revenue_unknown_count }} unknown)</td>
                                        <td class="p-3">{{ $result->result_status }}</td>
                                        <td class="p-3">
                                            <details>
                                                <summary class="cursor-pointer">View source IDs</summary>
                                                <dl class="mt-2 space-y-1 text-xs">
                                                    <dt class="font-semibold">Customers</dt><dd>{{ implode(', ', $result->source_snapshot['customer_ids'] ?? []) ?: 'None' }}</dd>
                                                    <dt class="font-semibold">Converted leads</dt><dd>{{ implode(', ', $result->source_snapshot['lead_ids'] ?? []) ?: 'None' }}</dd>
                                                    <dt class="font-semibold">Conversion events</dt><dd>{{ implode(', ', $result->source_snapshot['conversion_event_ids'] ?? []) ?: 'None' }}</dd>
                                                    <dt class="font-semibold">Cost entries</dt><dd>{{ implode(', ', $result->source_snapshot['cost_entry_ids'] ?? []) ?: 'None' }}</dd>
                                                    <dt class="font-semibold">Revenue rows</dt><dd>{{ implode(', ', $result->source_snapshot['revenue_ids'] ?? []) ?: 'None' }}</dd>
                                                    @foreach ($result->source_snapshot['cost_references'] ?? [] as $reference)
                                                        <dt class="font-semibold">Cost evidence</dt><dd>{{ $reference['source_reference'] ?? 'Unknown' }} · {{ $reference['evidence_reference'] ?? 'Unknown' }}</dd>
                                                    @endforeach
                                                </dl>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="p-4 text-gray-500">No attributable acquired customers or sourced acquisition costs were found for this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if (count($selectedRun->exceptions ?? []) > 0)
                        <section class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm dark:border-warning-700 dark:bg-warning-950">
                            <h3 class="font-semibold">Unknown, excluded, and unallocated inputs</h3>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach ($selectedRun->exceptions as $exception)
                                    <li>{{ str($exception['type'] ?? 'exception')->replace('_', ' ')->title() }}@if(isset($exception['customer_id'])) · customer #{{ $exception['customer_id'] }}@endif @if(isset($exception['lead_id'])) · lead #{{ $exception['lead_id'] }}@endif @if(isset($exception['cost_entry_id'])) · cost entry #{{ $exception['cost_entry_id'] }}@endif @if(isset($exception['revenue_id'])) · revenue row #{{ $exception['revenue_id'] }}@endif @if(isset($exception['reason'])) · {{ $exception['reason'] }}@endif</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    <section class="rounded-xl border border-gray-200 bg-white p-4 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200">Calculation policy</h3>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($selectedRun->policy_snapshot as $key => $value)
                                @if (is_string($value))
                                    <li><strong>{{ str($key)->replace('_', ' ')->title() }}:</strong> {{ $value }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </section>
                @else
                    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">No calculation runs yet. Record cost evidence and create a run to see results.</div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
