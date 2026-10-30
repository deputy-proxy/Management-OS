<div class="space-y-6">
    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Receivables and payables</h2>
                <p class="mt-1 max-w-4xl text-sm text-gray-600 dark:text-gray-300">
                    Read-only obligation and confirmed-allocation projection as of the selected date.
                    Confirmed allocations are payment evidence, not an automatic invoice settlement or status change.
                </p>
            </div>
            <label class="text-sm">Reporting date
                <input type="date" wire:model.live="asOf" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
            </label>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            Timezone: {{ $report['timezone'] }} · Generated: {{ $report['generated_at'] }}
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Supported receivables</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($report['summary']['supported_receivables']) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Unresolved receivables</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($report['summary']['unresolved_receivables']) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Vendor invoice records</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($report['summary']['vendor_invoice_records']) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Canonical payable total</p>
                <p class="mt-1 text-xl font-semibold">{{ $report['summary']['payable_total_contract_approved'] ? 'Approved' : 'Not approved' }}</p>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Receivables from issued customer invoices</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
            Balances use immutable issued invoice snapshots with a verified currency and linked legacy payment-allocation target.
            Active confirmed invoice allocations are subtracted; proposed and reversed allocations are excluded.
            A zero balance is labelled fully allocated, not paid.
        </p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Invoice / customer</th>
                        <th class="px-3 py-2">Due</th>
                        <th class="px-3 py-2">Currency</th>
                        <th class="px-3 py-2 text-right">Invoice total</th>
                        <th class="px-3 py-2 text-right">Confirmed allocations</th>
                        <th class="px-3 py-2 text-right">Outstanding</th>
                        <th class="px-3 py-2 text-right">Credit</th>
                        <th class="px-3 py-2">State / aging</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($report['receivables'] as $row)
                        <tr>
                            <td class="px-3 py-2">
                                @if ($row['legacy_customer_invoice_id'] && $this->customerInvoiceUrl($row['legacy_customer_invoice_id']))
                                    <a class="font-medium text-primary-600 underline dark:text-primary-400" href="{{ $this->customerInvoiceUrl($row['legacy_customer_invoice_id']) }}">{{ $row['source_reference'] }}</a>
                                @else
                                    <span class="font-medium">{{ $row['source_reference'] }}</span>
                                @endif
                                <span class="block text-xs text-gray-500">{{ $row['entity'] }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $row['due_date'] ?: 'Unknown' }}</td>
                            <td class="px-3 py-2">{{ $row['currency_code'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['invoice_total'] ?? 'Unavailable' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['confirmed_allocated'] ?? 'Unavailable' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['outstanding_amount'] ?? 'Unavailable' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['credit_amount'] ?? 'Unavailable' }}</td>
                            <td class="px-3 py-2">
                                <span class="font-medium">{{ str_replace('_', ' ', $row['balance_status']) }}</span>
                                <span class="block text-xs text-gray-500">{{ str_replace('_', ' ', $row['aging_bucket']) }}</span>
                                @if ($row['reason_codes'] !== [])
                                    <span class="block text-xs text-amber-700 dark:text-amber-400">{{ implode(', ', $row['reason_codes']) }}</span>
                                @endif
                            </td>
                        </tr>
                        @if ($row['allocation_history'] !== [])
                            <tr class="bg-gray-50/70 dark:bg-gray-800/40">
                                <td colspan="8" class="px-3 py-2 text-xs text-gray-500">
                                    Allocation history:
                                    @foreach ($row['allocation_history'] as $allocation)
                                        @if ($this->transactionUrl($allocation['transaction_id']))
                                            <a class="underline" href="{{ $this->transactionUrl($allocation['transaction_id']) }}">Transaction #{{ $allocation['transaction_id'] }}</a>
                                        @else
                                            Transaction #{{ $allocation['transaction_id'] }}
                                        @endif
                                        {{ $allocation['amount'] }} {{ $allocation['currency'] }} ({{ $allocation['status'] }})
                                        · {{ $allocation['reason'] }}
                                        @if ($allocation['confirmed_at'])
                                            · Confirmed {{ $allocation['confirmed_at'] }} by user #{{ $allocation['confirmed_by'] }}
                                        @endif
                                        @if ($allocation['reversed_at'])
                                            · Reversed {{ $allocation['reversed_at'] }} by user #{{ $allocation['reversed_by'] }}
                                        @endif
                                        @if ($allocation['reversal_reason'])
                                            · Reversal: {{ $allocation['reversal_reason'] }}
                                        @endif
                                        @if (! $loop->last); @endif
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="px-3 py-4 text-gray-500">No customer invoice records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h4 class="mt-5 text-sm font-semibold">Supported receivable totals by currency</h4>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Currency</th>
                        <th class="px-3 py-2 text-right">Invoices</th>
                        <th class="px-3 py-2 text-right">Gross total</th>
                        <th class="px-3 py-2 text-right">Allocated</th>
                        <th class="px-3 py-2 text-right">Outstanding</th>
                        <th class="px-3 py-2 text-right">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($report['receivable_totals'] as $currency => $total)
                        <tr>
                            <td class="px-3 py-2">{{ $currency }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($total['invoice_count']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $total['invoice_total'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $total['confirmed_allocated'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $total['outstanding'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $total['credit'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-3 text-gray-500">No receivable totals have supported inputs.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Payables and vendor invoice records</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
            Vendor payable totals remain unavailable because the canonical payable amount is not approved.
            This register shows explicit contractor workflow state, payable date, aging, and confirmed payment allocations as a separate fact.
            Allocation amounts are not subtracted from an unknown payable total and do not mark a vendor invoice paid.
        </p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2">Vendor invoice</th>
                        <th class="px-3 py-2">Workflow</th>
                        <th class="px-3 py-2">Payable date</th>
                        <th class="px-3 py-2">Currency</th>
                        <th class="px-3 py-2 text-right">Payable amount</th>
                        <th class="px-3 py-2 text-right">Confirmed allocations</th>
                        <th class="px-3 py-2">Payment state / aging</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($report['payables'] as $row)
                        <tr>
                            <td class="px-3 py-2">
                                @if ($this->vendorInvoiceUrl($row['source_id']))
                                    <a class="font-medium text-primary-600 underline dark:text-primary-400" href="{{ $this->vendorInvoiceUrl($row['source_id']) }}">{{ $row['source_reference'] }}</a>
                                @else
                                    <span class="font-medium">{{ $row['source_reference'] }}</span>
                                @endif
                                <span class="block text-xs text-gray-500">{{ $row['entity'] }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $row['workflow_status'] }}</td>
                            <td class="px-3 py-2">{{ $row['due_date'] ?: 'Unknown' }}</td>
                            <td class="px-3 py-2">{{ $row['currency_code'] }}</td>
                            <td class="px-3 py-2 text-right">Unavailable</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['confirmed_allocated'] ?? 'Needs review' }}</td>
                            <td class="px-3 py-2">
                                <span class="font-medium">{{ str_replace('_', ' ', $row['payment_state']) }}</span>
                                <span class="block text-xs text-gray-500">{{ str_replace('_', ' ', $row['aging_bucket']) }}</span>
                                <span class="block text-xs text-amber-700 dark:text-amber-400">{{ implode(', ', $row['reason_codes']) }}</span>
                            </td>
                        </tr>
                        @if ($row['allocation_history'] !== [])
                            <tr class="bg-gray-50/70 dark:bg-gray-800/40">
                                <td colspan="7" class="px-3 py-2 text-xs text-gray-500">
                                    Allocation history:
                                    @foreach ($row['allocation_history'] as $allocation)
                                        @if ($this->transactionUrl($allocation['transaction_id']))
                                            <a class="underline" href="{{ $this->transactionUrl($allocation['transaction_id']) }}">Transaction #{{ $allocation['transaction_id'] }}</a>
                                        @else
                                            Transaction #{{ $allocation['transaction_id'] }}
                                        @endif
                                        {{ $allocation['amount'] }} {{ $allocation['currency'] }} ({{ $allocation['status'] }})
                                        · {{ $allocation['reason'] }}
                                        @if ($allocation['confirmed_at'])
                                            · Confirmed {{ $allocation['confirmed_at'] }} by user #{{ $allocation['confirmed_by'] }}
                                        @endif
                                        @if ($allocation['reversed_at'])
                                            · Reversed {{ $allocation['reversed_at'] }} by user #{{ $allocation['reversed_by'] }}
                                        @endif
                                        @if ($allocation['reversal_reason'])
                                            · Reversal: {{ $allocation['reversal_reason'] }}
                                        @endif
                                        @if (! $loop->last); @endif
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="px-3 py-4 text-gray-500">No vendor invoice records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="text-xs text-gray-500">
        This report is read-only and never rewrites invoice status. Draft/cancelled documents are not obligations.
        Credit/debit notes and corrected invoices remain unresolved until a reviewed netting rule exists.
        Legacy customer invoice amounts and all vendor payable totals remain unresolved; no FX conversion or cross-currency aggregation is performed.
        Reversed and proposed allocations are retained in history but excluded from confirmed totals.
    </p>
</div>
