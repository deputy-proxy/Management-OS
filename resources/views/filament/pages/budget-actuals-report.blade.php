<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-950 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
            <strong>Provisional cash-movement comparison.</strong>
            Actuals use classified transaction cash movements only. They are not recognized revenue, accrual accounting, or reconciled financial actuals. No FX conversion or dimension inference is performed. Missing and excluded values remain visible.
        </div>

        <form wire:submit="generateReport" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-sm font-medium">Budget version
                    <select wire:model.live="selectedVersionId" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Select a budget version</option>
                        @foreach ($versions as $version)
                            <option value="{{ $version->id }}">{{ $version->budget?->name ?? 'Budget #'.$version->budget_id }} · v{{ $version->version }} · {{ $version->status }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-medium text-gray-900 dark:text-gray-100">Currency</span>
                    <p class="mt-2">{{ $selectedVersion?->currency_code ?? 'Select a version' }} · FX conversion unavailable</p>
                </div>
                <label class="text-sm font-medium">Reporting period start
                    <input type="date" wire:model="periodStart" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>
                <label class="text-sm font-medium">Reporting period end
                    <input type="date" wire:model="periodEnd" required min="{{ $periodStart }}" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>
                <label class="text-sm font-medium">Dimension
                    <select wire:model="dimensionKey" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="">All dimensions</option>
                        @foreach (($selectedVersion?->dimensions ?? []) as $key => $value)
                            <option value="{{ $key }}">{{ str($key)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium">Dimension value
                    <input type="text" wire:model="dimensionValue" placeholder="Exact value from the budget version" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>
            </div>
            <div class="mt-4">
                <x-filament::button type="submit" :disabled="! $selectedVersion">Generate comparison</x-filament::button>
            </div>
        </form>

        @if ($versions->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                No budget versions are available. Create a budget version before generating a comparison.
            </div>
        @endif

        @if ($reportRuns->isNotEmpty())
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="font-semibold">Saved comparison snapshots</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($reportRuns as $run)
                        <x-filament::button color="gray" size="sm" wire:click="openSnapshot({{ $run->id }})">
                            Snapshot #{{ $run->id }} · {{ $run->created_at->format('d M Y H:i') }}
                        </x-filament::button>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($report)
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $selectedVersion?->budget?->name }} · Version {{ $report['budget_version'] }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $report['period_start'] }} to {{ $report['period_end'] }} · {{ $report['currency_code'] }}</p>
                        <p class="mt-1 text-xs text-gray-500">Snapshot #{{ $report['report_run_id'] }} · Basis: {{ $report['actuals_basis_label'] }}</p>
                    </div>
                    <span class="rounded-full bg-warning-100 px-3 py-1 text-xs font-semibold text-warning-800">PROVISIONAL</span>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left dark:bg-gray-800">
                            <tr>
                                <th class="p-3">Budget line</th>
                                <th class="p-3">Dimensions</th>
                                <th class="p-3">Budget</th>
                                <th class="p-3">Actual cash movements</th>
                                <th class="p-3">Variance</th>
                                <th class="p-3">Variance %</th>
                                <th class="p-3">Status / sources</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['rows'] as $row)
                                <tr class="border-t border-gray-100 align-top dark:border-gray-800">
                                    <td class="p-3">
                                        @php($lineUrl = $this->budgetLineUrl((int) $row['line_item_id']))
                                        @if ($lineUrl)<a href="{{ $lineUrl }}" class="font-semibold text-primary-600 hover:underline">{{ $row['code'] ?: 'Uncoded' }} · Line #{{ $row['line_item_id'] }}</a>@else <strong>{{ $row['code'] ?: 'Uncoded' }} · Line #{{ $row['line_item_id'] }}</strong>@endif
                                        <div class="text-gray-500">{{ $row['description'] }}</div>
                                    </td>
                                    <td class="p-3">{{ collect($row['dimensions'])->map(fn ($value, $key) => $key.': '.$value)->implode(', ') ?: 'Unsegmented' }}</td>
                                    <td class="p-3">{{ $row['budget_amount'] }} {{ $report['currency_code'] }}</td>
                                    <td class="p-3">{{ $row['actual_amount'] ?? 'Unknown' }}</td>
                                    <td class="p-3">{{ $row['variance_amount'] ?? 'Withheld' }}</td>
                                    <td class="p-3">{{ $row['variance_percent'] === null ? 'Withheld' : $row['variance_percent'].'%' }}</td>
                                    <td class="p-3">
                                        <div>{{ str($row['status'])->replace('_', ' ')->title() }}</div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            @forelse ($row['source_transaction_ids'] as $sourceId)
                                                @php($sourceUrl = $this->transactionUrl((int) $sourceId))
                                                @if ($sourceUrl)<a href="{{ $sourceUrl }}" class="text-primary-600 hover:underline">Transaction #{{ $sourceId }}</a>@else Transaction #{{ $sourceId }}@endif{{ !$loop->last ? ', ' : '' }}
                                            @empty No source transactions @endforelse
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="p-4 text-gray-500">No budget lines match this dimension selection.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="font-semibold">Excluded and unknown source records</h2>
                <p class="mt-1 text-sm text-gray-500">Known excluded amount in {{ $report['currency_code'] }}: {{ $report['excluded_known_amount'] ?? 'Unknown' }} ({{ $report['excluded_known_amount_count'] }} records)</p>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($report['excluded'] as $item)
                        <li class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <strong>Transaction #{{ $item['source_id'] }}</strong> · {{ $item['reason'] }}
                            @if ($item['amount'] !== null) · {{ $item['amount'] }} {{ $item['currency'] ?? 'currency unknown' }} @endif
                            @if (isset($item['classification_code'])) · classification {{ $item['classification_code'] }} @endif
                        </li>
                    @empty
                        <li class="text-gray-500">No excluded records in this period.</li>
                    @endforelse
                </ul>
                <p class="mt-3 text-xs text-gray-500">Generated {{ $report['generated_at'] }}. This report is saved as an immutable snapshot. Later source corrections create a new report run and do not rewrite this result.</p>
            </section>
        @endif
    </div>
</x-filament-panels::page>