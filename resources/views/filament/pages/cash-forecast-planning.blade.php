<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-950 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
            <strong>Forecasts are scenarios, not actual balances.</strong>
            Actual movements use canonical transaction amounts. Supported issued-invoice receivables are projected on their due dates as medium-confidence assumptions. Vendor payable totals are currently unresolved by the source contract, so no payable amount is inferred. Enter verified expected outflows manually. Currencies are never combined or converted.
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <label class="block text-sm font-medium">Existing forecast scenario
                <select wire:model.live="selectedScenarioId" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Create a new forecast</option>
                    @foreach ($scenarios as $scenario)
                        <option value="{{ $scenario->id }}">{{ $scenario->forecast?->name }} · {{ $scenario->scenario_name }} · v{{ $scenario->version }} · {{ $scenario->status }}</option>
                    @endforeach
                </select>
            </label>
        </section>

        @if ((! $selectedScenarioId && hexa()->can('cash-forecast.create')) || ($selectedScenarioId && $selectedScenario?->status === 'draft' && hexa()->can('cash-forecast.update')))
        <form wire:submit="saveScenario" class="space-y-5 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-2">
                @if (! $selectedScenarioId)
                    <label class="text-sm font-medium">Forecast name
                        <input wire:model="forecastName" required maxlength="160" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                @else
                    <div class="text-sm"><span class="font-medium">Forecast</span><p class="mt-2">{{ $selectedScenario?->forecast?->name }}</p></div>
                @endif
                <label class="text-sm font-medium">Scenario name
                    <input wire:model="scenarioName" required maxlength="120" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>
                <label class="text-sm font-medium">Period start
                    <input type="date" wire:model="periodStart" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>
                <label class="text-sm font-medium">Period end
                    <input type="date" wire:model="periodEnd" required min="{{ $periodStart }}" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>
                <label class="text-sm font-medium">Currency code (3-letter)
                    <input wire:model="currencyCode" required minlength="3" maxlength="3" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>
                <label class="text-sm font-medium">Opening cash balance
                    <input type="number" step="0.01" wire:model="openingBalance" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>
            </div>
            <label class="block text-sm font-medium">Scenario assumptions and methodology
                <textarea wire:model="assumptions" rows="3" maxlength="4000" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"></textarea>
            </label>

            <div class="space-y-3">
                <div class="flex items-center justify-between"><h2 class="font-semibold">Manual forecast assumptions</h2><x-filament::button color="gray" size="sm" type="button" wire:click="addEntry">Add cash flow</x-filament::button></div>
                @foreach ($entries as $index => $entry)
                    <div wire:key="cash-flow-{{ $index }}" class="grid gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700 md:grid-cols-4">
                        <label class="text-sm">Direction<select wire:model="entries.{{ $index }}.direction" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"><option value="inflow">Expected inflow</option><option value="outflow">Expected outflow</option></select></label>
                        <label class="text-sm">Description<input wire:model="entries.{{ $index }}.description" maxlength="200" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"></label>
                        <label class="text-sm">Expected date<input type="date" wire:model="entries.{{ $index }}.expected_on" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"></label>
                        <label class="text-sm">Amount<input type="number" min="0.01" step="0.01" wire:model="entries.{{ $index }}.amount" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"></label>
                        <label class="text-sm">Confidence<select wire:model="entries.{{ $index }}.confidence" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option><option value="unknown">Unknown</option></select></label>
                        <label class="text-sm">Status<select wire:model="entries.{{ $index }}.status" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"><option value="assumed">Assumed</option><option value="confirmed">Confirmed</option><option value="at_risk">At risk</option><option value="unknown">Unknown</option></select></label>
                        <label class="text-sm md:col-span-2">Source / reference<input wire:model="entries.{{ $index }}.source_reference" maxlength="160" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"></label>
                        <div class="flex items-end"><x-filament::button color="danger" size="sm" type="button" wire:click="removeEntry({{ $index }})">Remove</x-filament::button></div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button type="submit">{{ $selectedScenarioId ? 'Save draft' : 'Create forecast' }}</x-filament::button>
            </div>
        </form>
        @endif

        @if ($selectedScenarioId)
            <section class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <span class="mr-2 text-sm text-gray-600 dark:text-gray-300">{{ $selectedScenario?->status === 'draft' ? 'Draft scenario' : 'Immutable '.$selectedScenario?->status.' scenario' }}</span>
                @if ($selectedScenario?->status === 'draft' && hexa()->can('cash-forecast.approve'))
                    <x-filament::button color="success" type="button" wire:click="approveScenario">Approve scenario</x-filament::button>
                @endif
                @if ($selectedScenario?->status === 'approved' && hexa()->can('cash-forecast.update'))
                    <x-filament::button color="gray" type="button" wire:click="createVersion">Create next version</x-filament::button>
                @endif
                @if (hexa()->can('cash-forecast.view'))
                    <x-filament::button color="gray" type="button" wire:click="generateReport">Generate forecast</x-filament::button>
                @endif
            </section>
        @endif

        @if ($selectedScenarioId && $runs->isNotEmpty())
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="mb-3 font-semibold">Saved forecast runs</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($runs as $run)
                        <x-filament::button color="gray" size="sm" type="button" wire:click="loadRun({{ $run->id }})">
                            {{ $run->created_at?->format('Y-m-d H:i') }} · as of {{ $run->as_of->toDateString() }}
                        </x-filament::button>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($report)
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div><h2 class="font-semibold">{{ $report['forecast_name'] }} · {{ $report['scenario_name'] }} · v{{ $report['version'] }}</h2><p class="text-sm text-gray-500">{{ $report['period_start'] }} to {{ $report['period_end'] }} · {{ $report['currency_code'] }} · As of {{ $report['as_of'] }}</p></div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs text-gray-500">Opening balance</div><div class="text-lg font-semibold">{{ $report['opening_balance'] }} {{ $report['currency_code'] }}</div></div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs text-gray-500">Scenario closing balance (actual + projected)</div><div class="text-lg font-semibold">{{ $report['closing_balance'] }} {{ $report['currency_code'] }}</div></div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs text-gray-500">Supported receivable assumptions</div><div class="text-lg font-semibold">{{ $report['sources']['supported_receivable_count'] }}</div></div>
                </div>
                <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-left dark:bg-gray-800"><tr><th class="p-2">Date</th><th class="p-2">Actual inflows</th><th class="p-2">Actual outflows</th><th class="p-2">Projected inflows</th><th class="p-2">Projected outflows</th><th class="p-2">Closing cash</th><th class="p-2">Sources</th></tr></thead><tbody>
                    @foreach ($report['rows'] as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-800"><td class="p-2">{{ $row['date'] }}</td><td class="p-2">{{ $row['actual_inflows'] }}</td><td class="p-2">{{ $row['actual_outflows'] }}</td><td class="p-2">{{ $row['projected_inflows'] }}</td><td class="p-2">{{ $row['projected_outflows'] }}</td><td class="p-2 font-semibold">{{ $row['closing_balance'] }}</td><td class="p-2"><div>{{ $row['actual_transaction_ids'] ? 'Transactions #'.implode(', #', $row['actual_transaction_ids']) : 'No actual source' }}</div><div class="text-xs text-gray-500">{{ collect($row['projected_sources'])->pluck('reference')->implode(', ') ?: 'No projected source' }}</div></td></tr>
                    @endforeach
                </tbody></table></div>
                <div class="space-y-2 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800"><strong>Source limitations</strong><p>{{ $report['sources']['actual_basis'] }}</p><p>{{ $report['sources']['receivable_basis'] }}</p><p>{{ $report['sources']['payable_basis'] }}</p><p>{{ $report['sources']['currency_basis'] }}</p><p>Manual assumptions recorded: {{ $report['manual_assumption_count'] }}.</p><p>Unmatched linked invoice payments withheld from projection: {{ $report['sources']['unresolved_receivable_payment_links'] }}. Vendor payable rows in the forecast window without an approved total: {{ $report['sources']['payables_unavailable_count'] }}. Vendor payable rows with missing amounts and no due date: {{ $report['sources']['undated_payables_missing_amount_count'] }}. Actual transactions with unresolved direction/amount/currency: {{ $report['sources']['unknown_actual_transaction_count'] }}. Actual transactions in other currencies, kept separate: {{ $report['sources']['other_currency_actual_transaction_count'] }}. Past manual assumptions excluded from projection: {{ $report['sources']['expired_manual_assumption_count'] }}. Manual assumptions duplicated by a supported receivable source: {{ $report['sources']['duplicate_manual_assumption_count'] }}.</p></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
