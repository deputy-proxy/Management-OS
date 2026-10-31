<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-950 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
            <strong>Provisional operational calculation only.</strong>
            This preview is not approved profitability, recognized revenue, statutory accounting profit, or a reconciled financial statement. Revenue and cost source eligibility, historical mapping, and finance-owner validation remain open. Missing or unallocated inputs suppress profit and margin rather than being treated as zero.
        </div>

        @if ($canCalculate)
            <form wire:submit="calculate" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Create a calculation run</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Each run stores its period, formula version, assumptions, source IDs, and exclusions. Corrections create a new run; previous runs are not edited.</p>
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

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
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
                @if ($selectedRun)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-semibold">Run {{ $selectedRun->run_id }}</h2>
                            <span class="rounded-full bg-warning-100 px-3 py-1 text-xs font-semibold text-warning-800">PROVISIONAL</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $selectedRun->period_start->format('d M Y') }} to {{ $selectedRun->period_end->format('d M Y') }} · {{ $selectedRun->formula_version }}</p>
                        <h3 class="mt-4 text-sm font-semibold">Assumptions</h3>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                            @foreach ($selectedRun->assumptions as $key => $value)
                                @if (is_string($value))
                                    <li><strong>{{ str($key)->replace('_', ' ')->title() }}:</strong> {{ $value }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                                <tr><th class="p-3">Product</th><th class="p-3">Currency</th><th class="p-3">Revenue</th><th class="p-3">Direct</th><th class="p-3">Overhead</th><th class="p-3">Provisional profit</th><th class="p-3">Margin</th><th class="p-3">Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($selectedRun->results as $result)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="p-3">
                                            <div>{{ $result->product?->title ?? 'Deleted / unavailable product' }}</div>
                                            <div class="mt-1 text-xs text-gray-500">Revenue rows: {{ implode(', ', $result->source_snapshot['revenue_ids'] ?? []) ?: 'none' }}</div>
                                            <div class="text-xs text-gray-500">Allocation snapshots: {{ implode(', ', $result->source_snapshot['cost_snapshot_ids'] ?? []) ?: 'none' }}</div>
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
                                    <tr><td colspan="8" class="p-4 text-gray-500">No products had usable canonical source inputs for this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <h3 class="font-semibold">Excluded and unallocated records</h3>
                        @if (count($selectedRun->exceptions ?? []) > 0)
                            <ul class="mt-2 space-y-2 text-sm">
                                @foreach ($selectedRun->exceptions as $exception)
                                    <li class="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                                        <strong>{{ str($exception['type'] ?? 'exception')->replace('_', ' ')->title() }}</strong>
                                        · {{ $exception['reason'] ?? ('Source record #'.($exception['source_id'] ?? $exception['snapshot_id'] ?? 'unknown')) }}
                                        @if (isset($exception['amount']))
                                            · {{ $exception['amount'] }} {{ $exception['currency'] ?? '' }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-gray-500">No excluded records were recorded for this run.</p>
                        @endif
                        <p class="mt-3 text-xs text-gray-500">Source IDs are retained in each result snapshot for audit and reconciliation. Currency totals are never combined.</p>
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">Select a calculation run or create the first one.</div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
