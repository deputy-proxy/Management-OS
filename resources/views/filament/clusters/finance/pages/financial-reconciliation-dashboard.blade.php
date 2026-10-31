<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Financial reconciliation diagnostics</h2>
                    <p class="mt-1 max-w-4xl text-sm text-gray-600 dark:text-gray-300">
                        Read-only diagnostics for completed bank-statement imports and their recorded review decisions.
                        Candidate matches are suggestions, not confirmed matches or proof of settlement. No source record is changed.
                    </p>
                </div>
                <div class="text-xs text-gray-500">Generated {{ $report['generated_at'] }} · Europe/Bucharest</div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-5">
                <label class="text-sm">From
                    <input type="date" wire:model.live="reportFrom" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                </label>
                <label class="text-sm">To
                    <input type="date" wire:model.live="reportTo" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                </label>
                <label class="text-sm">Financial account
                    <select wire:model.live="reportAccount" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                        <option value="">All accounts</option>
                        @foreach ($report['accounts'] as $account)
                            <option value="{{ $account['id'] }}">{{ $account['title'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">Currency
                    <select wire:model.live="reportCurrency" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                        <option value="">All currencies</option>
                        @foreach ($report['currencies'] as $currency)
                            <option value="{{ $currency }}">{{ $currency }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">Review status
                    <select wire:model.live="reportDecision" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                        <option value="">All statuses</option>
                        <option value="unreviewed">Unreviewed</option>
                        <option value="exact_match">Exact match decision</option>
                        <option value="partial_candidate">Partial candidate</option>
                        <option value="unmatched">Unmatched</option>
                        <option value="ambiguous">Ambiguous</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </label>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-4 lg:grid-cols-5">
                @foreach ([
                    'statement_rows' => 'Statement rows',
                    'unreviewed' => 'Unreviewed',
                    'exact_match' => 'Exact decisions',
                    'partial_candidate' => 'Partial candidates',
                    'ambiguous' => 'Ambiguous',
                    'unmatched' => 'Unmatched',
                    'rejected' => 'Rejected',
                    'exact_candidates' => 'Exact suggestions',
                    'near_candidates' => 'Near-date suggestions',
                    'partial_candidates' => 'Partial amount suggestions',
                ] as $key => $label)
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($report['summary'][$key] ?? 0) }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Statement totals by currency and direction</h2>
            <p class="mt-1 text-xs text-gray-500">Amounts are summed only within the same currency and direction. Exact-match amounts require a recorded manual exact-match decision; partial candidates remain unresolved.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2">Currency</th>
                            <th class="px-3 py-2">Direction</th>
                            <th class="px-3 py-2 text-right">Rows</th>
                            <th class="px-3 py-2 text-right">Statement amount</th>
                            <th class="px-3 py-2 text-right">Exact matched</th>
                            <th class="px-3 py-2 text-right">Partial candidate</th>
                            <th class="px-3 py-2 text-right">Unmatched</th>
                            <th class="px-3 py-2 text-right">Ambiguous</th>
                            <th class="px-3 py-2 text-right">Unreviewed</th>
                            <th class="px-3 py-2 text-right">Rejected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($report['totals_by_currency'] as $total)
                            <tr>
                                <td class="px-3 py-2">{{ $total['currency'] }}</td>
                                <td class="px-3 py-2">{{ $total['direction'] === 'money_in' ? 'Money in' : 'Money out' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($total['statement_rows']) }}</td>
                                @foreach (['statement_amount', 'exact_match_amount', 'partial_candidate_amount', 'unmatched_amount', 'ambiguous_amount', 'unreviewed_amount', 'rejected_amount'] as $amountKey)
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $total[$amountKey] }} {{ $total['currency'] }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-3 py-4 text-sm text-gray-500">No statement totals are available for these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Statement rows and candidate matches</h2>
                    <p class="mt-1 text-xs text-gray-500">Date range: {{ $report['from'] }} to {{ $report['to'] }} · Maximum 1,000 rows per report.</p>
                </div>
                @if (hexa()->can('transaction.update') && hexa()->can('transaction.index'))
                    <a href="{{ \App\Filament\Resources\BankStatementImportItems\BankStatementImportItemResource::getUrl('index') }}" class="text-sm font-medium text-primary-600 underline dark:text-primary-400">Open statement matching workflow</a>
                @endif
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2">Date / source</th>
                            <th class="px-3 py-2">Account</th>
                            <th class="px-3 py-2">Statement amount</th>
                            <th class="px-3 py-2">Review status</th>
                            <th class="px-3 py-2">Candidate transactions</th>
                            <th class="px-3 py-2">Decision / audit history</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($report['rows'] as $row)
                            <tr class="align-top">
                                <td class="px-3 py-3">
                                    <div class="font-medium">{{ $row['date'] }}</div>
                                    <div class="text-xs text-gray-500">Batch #{{ $row['batch_id'] }} · page {{ $row['source_page'] }}, row {{ $row['source_row'] }}</div>
                                    @if ($canViewSources)
                                        <a href="{{ $this->statementItemUrl($row['id']) }}" class="text-xs text-primary-600 underline dark:text-primary-400">Statement row #{{ $row['id'] }}</a>
                                    @else
                                        <span class="text-xs text-gray-500">Statement row #{{ $row['id'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-medium">{{ $row['account'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $row['bank'] }} · {{ $row['direction'] }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ $row['amount'] }} {{ $row['currency'] }}</td>
                                <td class="px-3 py-3">
                                    <span class="font-medium">{{ str_replace('_', ' ', ucfirst($row['status'])) }}</span>
                                    <div class="text-xs text-gray-500">Candidate scan: {{ str_replace('_', ' ', $row['candidate_status']) }}</div>
                                    @if ($row['review_reasons'] !== [])
                                        <div class="mt-1 text-xs text-warning-600">{{ implode(', ', $row['review_reasons']) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @forelse ($row['candidates'] as $candidate)
                                        <div class="mb-2 last:mb-0">
                                            @if ($canViewSources)
                                                <a href="{{ $this->transactionUrl($candidate['transaction_id']) }}" class="font-medium text-primary-600 underline dark:text-primary-400">Transaction #{{ $candidate['transaction_id'] }}</a>
                                            @else
                                                <span class="font-medium">Transaction #{{ $candidate['transaction_id'] }}</span>
                                            @endif
                                            <div class="text-xs text-gray-500">{{ $candidate['date'] }} · {{ $candidate['amount'] }} {{ $candidate['currency'] }} · {{ $candidate['match_type'] }}</div>
                                            <div class="text-xs text-gray-500">Allocation history: {{ $candidate['allocation_counts']['confirmed'] }} confirmed · {{ $candidate['allocation_counts']['proposed'] }} proposed · {{ $candidate['allocation_counts']['reversed'] }} reversed · {{ $candidate['allocation_counts']['rejected'] }} rejected</div>
                                            @if ($candidate['shared_candidate_count'] > 1)
                                                <div class="text-xs text-warning-600">Candidate is shared by {{ $candidate['shared_candidate_count'] }} statement rows</div>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-xs text-gray-500">No candidate found</span>
                                    @endforelse
                                </td>
                                <td class="px-3 py-3">
                                    @if ($row['decision'])
                                        <div class="font-medium">{{ str_replace('_', ' ', ucfirst($row['decision']['outcome'])) }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['decision']['target_type'] ? ucfirst(str_replace('_', ' ', $row['decision']['target_type'])).' #'.$row['decision']['target_id'] : 'No target' }}</div>
                                        <div class="mt-1 max-w-xs text-xs text-gray-600 dark:text-gray-300">{{ $row['decision']['reason'] }}</div>
                                    @else
                                        <span class="text-xs text-gray-500">No manual decision recorded</span>
                                    @endif
                                    @if (count($row['history']) > 1)
                                        <details class="mt-2 text-xs">
                                            <summary class="cursor-pointer text-primary-600">{{ count($row['history']) }} audit events</summary>
                                            @foreach ($row['history'] as $event)
                                                <div class="mt-2 border-l-2 border-gray-200 pl-2 dark:border-gray-700">
                                                    <div>{{ $event['outcome'] }} · {{ $event['recorded_at'] }}</div>
                                                    <div class="text-gray-500">{{ $event['actor'] ?? 'Unknown actor' }} · {{ $event['reason'] }}</div>
                                                </div>
                                            @endforeach
                                        </details>
                                    @elseif ($row['history'] !== [])
                                        <div class="mt-1 text-xs text-gray-500">{{ $row['history'][0]['recorded_at'] }} · {{ $row['history'][0]['actor'] ?? 'Unknown actor' }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">No completed statement rows match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Candidate suggestions require the same account, currency, direction and amount. Exact suggestions use the same calendar date; near-date and smaller-amount partial suggestions allow up to three calendar days. These are diagnostic heuristics, not approved matching rules. Multiple candidates remain ambiguous. A recorded manual decision always takes precedence over suggestions. No candidate is auto-confirmed, and no invoice status, transaction, allocation, or balance is changed.
            </p>
        </section>

        <p class="text-xs text-gray-500">
            This page is an application diagnostic, not an independently verified accounting reconciliation. It does not prove statement completeness, settlement, opening/closing balance, revenue, profit, receivables, or payables. Reversed payment allocations remain history and are not treated as active matches. All values remain separated by source currency; no FX conversion or inferred currency is applied.
        </p>
    </div>
</x-filament-panels::page>
