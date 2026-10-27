<x-filament-panels::page>
    @php($summary = app(\App\Services\FinancialOverviewData::class)->summary())

    <div class="space-y-6">
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
