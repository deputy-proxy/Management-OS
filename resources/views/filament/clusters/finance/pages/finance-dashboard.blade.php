<x-filament-panels::page>
    @php($summary = app(\App\Services\FinancialOverviewData::class)->summary())

    <div class="space-y-6">
    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Consolidated financial source activity</h2>
                <p class="mt-1 max-w-4xl text-sm text-gray-600 dark:text-gray-300">
                    Operational source records grouped by reporting month, category, entity and currency.
                    This report does not calculate recognized revenue, recognized expense, profit, receivables or payables.
                    Cash movements and issued-document amounts remain separate bases.
                </p>
            </div>
            <div class="text-xs text-gray-500">Reporting timezone: {{ $consolidatedReport['timezone'] }}</div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <label class="text-sm">From
                <input type="date" wire:model.live="reportFrom" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
            </label>
            <label class="text-sm">To
                <input type="date" wire:model.live="reportTo" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
            </label>
            <label class="text-sm">Source basis
                <select wire:model.live="reportBasis" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                    <option value="all">All supported sources</option>
                    <option value="customer_documents">Issued customer documents</option>
                    <option value="vendor_invoices">Vendor invoice records</option>
                    <option value="cash_movements">Canonical cash movements</option>
                </select>
            </label>
            <label class="text-sm">Currency code
                <input type="text" maxlength="3" placeholder="All currencies" wire:model.live.debounce.500ms="reportCurrency" class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase dark:border-gray-700 dark:bg-gray-950">
            </label>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Included source records</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($consolidatedReport['totals']['included']) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Excluded by report contract</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($consolidatedReport['totals']['excluded']) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Unknown / incomplete</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($consolidatedReport['totals']['unknown']) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Groups shown</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format(count($consolidatedReport['groups'])) }}</p>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Period</th>
                        <th class="px-3 py-2">Basis / category</th>
                        <th class="px-3 py-2">Entity</th>
                        <th class="px-3 py-2">Currency</th>
                        <th class="px-3 py-2 text-right">Supported amount</th>
                        <th class="px-3 py-2 text-right">Included</th>
                        <th class="px-3 py-2 text-right">Excluded</th>
                        <th class="px-3 py-2 text-right">Unknown</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($consolidatedReport['groups'] as $group)
                        <tr>
                            <td class="px-3 py-2">{{ $group['period'] }}</td>
                            <td class="px-3 py-2">
                                <span class="font-medium">{{ str_replace('_', ' ', ucfirst($group['basis'])) }}</span>
                                <span class="block text-xs text-gray-500">{{ $group['category'] }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $group['entity'] }}</td>
                            <td class="px-3 py-2">{{ $group['currency'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                @if ($group['amount'] !== null)
                                    {{ $group['amount'] }} {{ $group['currency'] }}
                                @else
                                    <span class="text-gray-500">Unavailable</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($group['included_count']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($group['excluded_count']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($group['unknown_count']) }}</td>
                        </tr>
                        <tr class="bg-gray-50/70 dark:bg-gray-800/40">
                            <td colspan="8" class="px-3 py-2 text-xs text-gray-500">
                                Source records:
                                @foreach ($group['sources'] as $source)
                                    @if ($source['url'])
                                        <a href="{{ $source['url'] }}" class="font-medium text-primary-600 underline dark:text-primary-400">{{ $source['label'] }}</a>@if (! $loop->last), @endif
                                    @else
                                        <span>{{ $source['label'] }}</span>@if (! $loop->last), @endif
                                    @endif
                                @endforeach
                                @if ($group['reasons'] !== [])
                                    · Reasons: {{ implode(', ', $group['reasons']) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-4 text-sm text-gray-500">No source records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-gray-500">
            Issued customer-document amounts use the immutable issued snapshot gross total and issue date; invoice, credit-note and debit-note categories are not netted.
            Vendor invoice amounts remain unavailable because the canonical payable total is not approved.
            Legacy customer invoice amounts remain outside monetary totals because their currency and units are unresolved.
            Cash movements use only explicit direction, non-negative canonical decimal amount and a three-letter currency code; they are not revenue or expense.
            Source IDs are included for drill-down and audit. Unknown records are never converted to zero.
        </p>
    </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recorded transactions</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Monthly totals grouped by transaction type and the linked financial account currency. Amounts use the units recorded in the database; no conversion or assumed decimal scaling is applied.
            </p>
            @if (count($summary['transactions']) === 0)
                <p class="mt-4 text-sm text-gray-500">No transactions recorded for {{ $summary['period'] }} or {{ $summary['previous_period'] }}.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Currency</th>
                                <th class="px-3 py-2 text-right">{{ $summary['previous_period'] }}</th>
                                <th class="px-3 py-2 text-right">{{ $summary['period'] }}</th>
                                <th class="px-3 py-2 text-right">Records this month</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($summary['transactions'] as $transaction)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $transaction['type'] !== '' ? $transaction['type'] : 'Unspecified' }}</td>
                                    <td class="px-3 py-2">{{ $transaction['currency'] !== '' ? $transaction['currency'] : 'Unspecified' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($transaction['previous_total']) }} <span class="text-gray-500">({{ $transaction['previous_count'] }})</span></td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($transaction['current_total']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $transaction['current_count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Canonical cash movements</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Only transactions with explicit direction, non-negative decimal amount, and a three-letter currency are included. These are recorded cash-movement amounts, not revenue recognition, payable settlement, profit, or bank reconciliation.</p>
            @forelse ($summary['canonical_cash_movements']['rows'] as $movement)
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                    <div><p class="font-medium">{{ $movement['currency'] }} · {{ $movement['direction'] === 'money_in' ? 'Money in' : 'Money out' }}</p><p class="text-xs text-gray-500">Current count: {{ number_format($movement['current_count']) }} · Previous count: {{ number_format($movement['previous_count']) }}</p></div>
                    <div class="text-right tabular-nums"><p>{{ number_format((float) $movement['current_amount'], 2) }} {{ $movement['currency'] }}</p><p class="text-xs text-gray-500">Previous: {{ number_format((float) $movement['previous_amount'], 2) }} {{ $movement['currency'] }}</p></div>
                </div>
            @empty
                <p class="mt-4 text-sm text-gray-500">No canonical cash-movement rows are available for these periods.</p>
            @endforelse
            <p class="mt-3 text-xs text-gray-500">Excluded for missing/invalid canonical direction, amount, or currency: current {{ number_format($summary['canonical_cash_movements']['excluded_current_count']) }}, previous {{ number_format($summary['canonical_cash_movements']['excluded_previous_count']) }}. No currency conversion is performed.</p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Transaction classification coverage</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Counts use the explicit money direction and legacy type label. This does not sum money or infer refunds, reversals, transfers, or settlement from a link.</p>
            @forelse ($summary['transaction_classifications'] as $classification)
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                    <div><p class="font-medium">{{ $classification['label'] }}</p><p class="text-xs text-gray-500">{{ $classification['code'] }} · {{ $classification['status'] }}</p></div>
                    <div class="text-right tabular-nums"><p>Current: {{ number_format($classification['current_count']) }}</p><p class="text-xs text-gray-500">Previous: {{ number_format($classification['previous_count']) }}</p></div>
                </div>
            @empty
                <p class="mt-4 text-sm text-gray-500">No transaction classification records for these periods.</p>
            @endforelse
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Customer invoice status</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Invoice counts for {{ $summary['period'] }}, grouped by the stored status. Amounts are omitted because customer invoices have no currency field.</p>
                @forelse ($summary['customer_invoice_statuses'] as $status)
                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span>{{ $status['status'] !== '' ? $status['status'] : 'Unspecified' }}</span>
                        <span class="font-semibold tabular-nums">{{ $status['count'] }}</span>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-gray-500">No customer invoices recorded for this period.</p>
                @endforelse
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Vendor invoice status</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Invoice counts for {{ $summary['period'] }}, grouped by stored status and currency. Status values are shown as recorded because no controlled payable-state list exists.</p>
                @forelse ($summary['vendor_invoice_statuses'] as $status)
                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span>{{ $status['status'] !== '' ? $status['status'] : 'Unspecified' }} · {{ $status['currency'] !== '' ? $status['currency'] : 'Currency unspecified' }}</span>
                        <span class="font-semibold tabular-nums">{{ $status['count'] }}</span>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-gray-500">No vendor invoices recorded for this period.</p>
                @endforelse
            </section>
        </div>

        <p class="text-xs text-gray-500">
            Scope: non-deleted records only; transaction periods use the transaction timestamp, and invoice periods use each invoice's billing month. Product revenue/cost, invoice balances, margin, and cash balance are not combined here because their currency, amount-unit, or accounting semantics are not consistently defined in the current schema.
        </p>
    </div>
</x-filament-panels::page>
