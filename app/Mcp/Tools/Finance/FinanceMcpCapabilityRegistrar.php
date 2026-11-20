<?php

namespace App\Mcp\Tools\Finance;

use App\Mcp\Authorization\AuthorizedMcpCapabilityRegistrar;
use App\Models\BudgetVersion;
use App\Models\CashForecastScenario;
use App\Models\ProfitabilityCalculationRun;
use App\Models\User;
use App\Services\BudgetActualsComparisonService;
use App\Services\CashForecastService;
use App\Services\ConsolidatedFinancialReportData;
use App\Services\Finance\FinancialReconciliationReportData;
use App\Services\Finance\ReceivablesPayablesData;
use App\Services\FinancialOverviewData;
use App\Services\ProfitabilityReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Mcp\Server\Builder;

final class FinanceMcpCapabilityRegistrar extends AuthorizedMcpCapabilityRegistrar
{
    private const TZ = 'Europe/Bucharest';

    public function register(Builder $builder): void
    {
        $tools = [
            'get_financial_overview' => fn (User $u, array $a): array => $this->overview($u),
            'get_transactions' => fn (User $u, array $a): array => $this->transactions($u, $a),
            'get_receivables' => fn (User $u, array $a): array => $this->receivables($u, $a),
            'get_payables' => fn (User $u, array $a): array => $this->payables($u, $a),
            'get_cash_forecast' => fn (User $u, array $a): array => $this->cashForecast($u, $a),
            'get_budget_vs_actuals' => fn (User $u, array $a): array => $this->budgetActuals($u, $a),
            'get_profitability_report' => fn (User $u, array $a): array => $this->profitability($u, $a),
            'get_reconciliation_status' => fn (User $u, array $a): array => $this->reconciliation($u, $a),
            'get_invoice_details' => fn (User $u, array $a): array => $this->invoice($u, $a),
        ];
        $descriptions = [
            'get_financial_overview' => 'Read-only finance overview with explicit legacy-unit and source availability semantics.',
            'get_transactions' => 'Lists bounded canonical cash movements without party identity or internal source identifiers.',
            'get_receivables' => 'Lists supported issued-invoice receivables and unresolved legacy invoice records.',
            'get_payables' => 'Lists vendor invoice metadata while keeping unsupported payable totals unavailable.',
            'get_cash_forecast' => 'Reads an approved forecast scenario without generating or persisting a run.',
            'get_budget_vs_actuals' => 'Previews an approved budget version without persisting a report snapshot.',
            'get_profitability_report' => 'Reads a completed immutable profitability run without customer-level detail.',
            'get_reconciliation_status' => 'Returns statement status and candidate hints without changing reconciliation decisions.',
            'get_invoice_details' => 'Returns a narrow permission-filtered invoice view without party identity or internal metadata.',
        ];
        foreach ($tools as $name => $handler) {
            $this->addAuthorizedTool($builder, $name, $handler, $descriptions[$name], $this->schema($name));
        }
    }

    private function has(User $user, string $permission): bool
    {
        return $this->authorization->hasDomainPermission($user, $permission);
    }

    private function page(array $rows, array $v, string $key = 'rows'): array
    {
        $offset = (int) ($v['offset'] ?? 0);
        $limit = (int) ($v['limit'] ?? 50);
        $total = count($rows);

        return [
            'total_count' => $total,
            'returned_count' => count(array_slice($rows, $offset, $limit)),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $total > $offset + $limit,
            $key => array_values(array_slice($rows, $offset, $limit)),
        ];
    }

    private function overview(User $user): array
    {
        return $this->withPrincipalContext($user, function () use ($user): array {
            $r = app(FinancialOverviewData::class)->summary(CarbonImmutable::now(self::TZ));
            $tx = $this->has($user, 'transaction.index');
            $customer = $this->has($user, 'customer_invoice.index');
            $vendor = $this->has($user, 'vendor_invoice.index');

            return [
                'availability' => 'available', 'as_of' => CarbonImmutable::now(self::TZ)->toIso8601String(),
                'timezone' => self::TZ, 'period' => $r['period'], 'previous_period' => $r['previous_period'],
                'legacy_transactions' => $tx ? array_map(fn (array $x): array => [
                    'currency' => $x['currency'], 'type' => $x['type'], 'current_total' => $x['current_total'],
                    'previous_total' => $x['previous_total'], 'current_count' => $x['current_count'],
                    'previous_count' => $x['previous_count'], 'amount_unit' => 'legacy_integer_unverified',
                ], $r['transactions']) : [],
                'canonical_cash_movements' => $tx ? [
                    'availability' => 'available', 'basis' => 'canonical_decimal_cash_movements',
                    'rows' => $r['canonical_cash_movements']['rows'],
                    'excluded_current_count' => $r['canonical_cash_movements']['excluded_current_count'],
                    'excluded_previous_count' => $r['canonical_cash_movements']['excluded_previous_count'],
                ] : ['availability' => 'unavailable_permission_denied', 'basis' => 'canonical_decimal_cash_movements', 'rows' => [], 'excluded_current_count' => null, 'excluded_previous_count' => null],
                'transaction_classifications' => $tx ? $r['transaction_classifications'] : [],
                'customer_invoice_statuses' => $customer ? $r['customer_invoice_statuses'] : [],
                'vendor_invoice_statuses' => $vendor ? $r['vendor_invoice_statuses'] : [],
                'source_availability' => [
                    'legacy_transactions' => $tx ? 'available_legacy_units_unverified' : 'unavailable_permission_denied',
                    'canonical_cash_movements' => $tx ? 'available' : 'unavailable_permission_denied',
                    'transaction_classifications' => $tx ? 'available' : 'unavailable_permission_denied',
                    'customer_invoice_statuses' => $customer ? 'available' : 'unavailable_permission_denied',
                    'vendor_invoice_statuses' => $vendor ? 'available' : 'unavailable_permission_denied',
                ],
            ];
        });
    }

    private function transactions(User $user, array $a): array
    {
        $v = Validator::make($a, [
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'currency_code' => ['sometimes', 'nullable', 'regex:/^[A-Z]{3}$/'],
            'direction' => ['sometimes', Rule::in(['money_in', 'money_out', 'unknown'])],
            'category' => ['sometimes', Rule::in(['customer_money_in', 'customer_money_out', 'vendor_money_in', 'vendor_money_out', 'tax_money_in', 'tax_money_out', 'other_money_in', 'other_money_out', 'unclassified_legacy'])],
            'account_id' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            $from = CarbonImmutable::parse($v['start_date'], self::TZ);
            $to = CarbonImmutable::parse($v['end_date'], self::TZ);
            if ($from->diffInDays($to) > 90) {
                return ['status' => 'unavailable_date_range_exceeds_limit', 'from' => $v['start_date'], 'to' => $v['end_date'], 'timezone' => self::TZ, 'maximum_days' => 90, 'total_count' => null, 'returned_count' => 0, 'records' => []];
            }
            if (isset($v['account_id']) && ! $this->has($user, 'financial_account.index')) {
                return ['status' => 'unavailable_permission_denied', 'records' => []];
            }
            $filters = ['account_id' => $v['account_id'] ?? null, 'direction' => $v['direction'] ?? null, 'category' => $v['category'] ?? null];
            $r = app(ConsolidatedFinancialReportData::class)->report($from, $to, $v['currency_code'] ?? null, 'cash_movements', $filters);
            $rows = array_map(fn (array $x): array => [
                'source_type' => $x['source_type'], 'date' => $x['source_date'], 'direction' => $x['direction'],
                'category' => $x['category'], 'currency_code' => $x['currency'], 'amount' => $x['amount'],
                'amount_status' => $x['status'], 'reason_code' => $x['reason'],
                'classification_label' => $x['classification_label'], 'classification_status' => $x['classification_status'],
                'account_label' => $this->has($user, 'financial_account.index') ? ($x['entity'] ?? null) : null,
                'duplicate_status' => $x['duplicate_status'], 'duplicate_count' => $x['duplicate_count'],
                'special_event_status' => $x['special_event_status'],
            ], $r['records']);

            return ['status' => 'available', 'from' => $r['from'], 'to' => $r['to'], 'timezone' => self::TZ, 'basis' => 'canonical_cash_movements',
                'totals' => $r['totals'], ...$this->page($rows, $v, 'records')];
        });
    }

    private function safeInvoice(array $r, bool $payments): array
    {
        return [
            'source_type' => $r['source_type'],
            'source_reference' => $r['source_type'] === 'issued_customer_invoice' ? ($r['source_reference'] ?? null) : null,
            'document_date' => $r['document_date'] ?? null, 'due_date' => $r['due_date'] ?? null,
            'currency_code' => $r['currency_code'] ?? 'Unknown', 'invoice_total' => $r['invoice_total'] ?? null,
            'confirmed_allocated' => $payments ? ($r['confirmed_allocated'] ?? null) : null,
            'outstanding_amount' => $payments ? ($r['outstanding_amount'] ?? null) : null,
            'credit_amount' => $payments ? ($r['credit_amount'] ?? null) : null,
            'balance_status' => $r['balance_status'] ?? 'needs_review',
            'payment_state' => $payments ? ($r['payment_state'] ?? 'unresolved') : 'unavailable_permission_denied',
            'aging_bucket' => $r['aging_bucket'] ?? 'unknown', 'days_overdue' => $r['days_overdue'] ?? null,
            'allocation_count' => $payments ? ($r['allocation_count'] ?? null) : null,
            'pending_allocation_count' => $payments ? ($r['pending_allocation_count'] ?? null) : null,
            'reversed_allocation_count' => $payments ? ($r['reversed_allocation_count'] ?? null) : null,
            'reason_codes' => $r['reason_codes'] ?? [], 'document_status' => $r['document_status'] ?? null,
            'source_status' => $r['source_status'] ?? null,
            'payable_amount_availability' => 'unavailable_vendor_payable_total_not_approved',
            'payment_data_availability' => $payments ? 'available' : 'unavailable_permission_denied',
        ];
    }

    private function receivables(User $user, array $a): array
    {
        $v = Validator::make($a, ['as_of' => ['sometimes', 'date_format:Y-m-d'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:100'], 'offset' => ['sometimes', 'integer', 'min:0', 'max:1000']])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            $r = app(ReceivablesPayablesData::class)->report(CarbonImmutable::parse($v['as_of'] ?? 'today', self::TZ));
            $payments = $this->has($user, 'transaction.index');
            $rows = array_map(fn (array $x): array => $this->safeInvoice($x, $payments), $r['receivables']);
            $totals = [];
            foreach ($r['receivable_totals'] as $currency => $x) {
                $totals[] = [
                    'currency_code' => $currency, 'invoice_total' => $x['invoice_total'],
                    'confirmed_allocated' => $payments ? $x['confirmed_allocated'] : null,
                    'outstanding_amount' => $payments ? $x['outstanding'] : null,
                    'credit_amount' => $payments ? $x['credit'] : null, 'invoice_count' => $x['invoice_count'],
                ];
            }

            return ['availability' => 'available', 'as_of' => $r['as_of'], 'timezone' => self::TZ, 'generated_at' => $r['generated_at'],
                'payment_data_availability' => $payments ? 'available' : 'unavailable_permission_denied', 'totals_by_currency' => $totals,
                'summary' => ['supported_receivables' => $r['summary']['supported_receivables'], 'unresolved_receivables' => $r['summary']['unresolved_receivables'], 'invoice_record_count' => count($rows)],
                ...$this->page($rows, $v, 'receivables')];
        });
    }

    private function payables(User $user, array $a): array
    {
        $v = Validator::make($a, ['as_of' => ['sometimes', 'date_format:Y-m-d'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:100'], 'offset' => ['sometimes', 'integer', 'min:0', 'max:1000']])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            $r = app(ReceivablesPayablesData::class)->report(CarbonImmutable::parse($v['as_of'] ?? 'today', self::TZ));
            $payments = $this->has($user, 'transaction.index');
            $rows = array_map(fn (array $x): array => $this->safeInvoice($x, $payments), $r['payables']);

            return ['availability' => 'available', 'as_of' => $r['as_of'], 'timezone' => self::TZ, 'generated_at' => $r['generated_at'],
                'amount_availability' => 'unavailable_vendor_payable_total_not_approved',
                'payment_data_availability' => $payments ? 'available' : 'unavailable_permission_denied',
                'summary' => ['vendor_invoice_records' => count($rows), 'payable_total_contract_approved' => false],
                ...$this->page($rows, $v, 'payables')];
        });
    }

    private function cashForecast(User $user, array $a): array
    {
        $v = Validator::make($a, ['scenario_id' => ['required', 'integer', 'min:1'], 'as_of' => ['sometimes', 'date_format:Y-m-d'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:366'], 'offset' => ['sometimes', 'integer', 'min:0', 'max:730']])->validate();

        return $this->withPrincipalContext($user, function () use ($v): array {
            $scenario = CashForecastScenario::query()->whereKey($v['scenario_id'])->whereIn('status', [CashForecastScenario::STATUS_APPROVED, CashForecastScenario::STATUS_RETIRED])->first();
            if (! $scenario) {
                return ['availability' => 'unavailable_scenario_not_approved'];
            }
            $r = app(CashForecastService::class)->report($scenario, isset($v['as_of']) ? CarbonImmutable::parse($v['as_of'], self::TZ) : null);
            $rows = array_map(fn (array $x): array => [
                'date' => $x['date'], 'actual_inflows' => $x['actual_inflows'], 'actual_outflows' => $x['actual_outflows'],
                'projected_inflows' => $x['projected_inflows'], 'projected_outflows' => $x['projected_outflows'],
                'closing_balance' => $x['closing_balance'], 'actual_transaction_count' => count($x['actual_transaction_ids']),
                'projected_sources' => array_map(fn (array $s): array => [
                    'type' => $s['type'], 'amount' => $s['amount'], 'confidence' => $s['confidence'],
                    'status' => $s['status'],
                ], $x['projected_sources']),
            ], $r['rows']);
            $sources = Arr::only($r['sources'], ['supported_receivable_count', 'unresolved_receivable_payment_links', 'payables_unavailable_count', 'undated_payables_missing_amount_count', 'unknown_actual_transaction_count', 'other_currency_actual_transaction_count', 'expired_manual_assumption_count', 'duplicate_manual_assumption_count', 'actual_basis', 'receivable_basis', 'payable_basis', 'currency_basis']);

            return [
                'availability' => 'available', 'timezone' => self::TZ, 'forecast_name' => $r['forecast_name'], 'scenario_name' => $r['scenario_name'],
                'version' => $r['version'], 'scenario_status' => $r['status'], 'period_start' => $r['period_start'], 'period_end' => $r['period_end'],
                'currency_code' => $r['currency_code'], 'opening_balance' => $r['opening_balance'], 'closing_balance' => $r['closing_balance'],
                'as_of' => $r['as_of'], 'manual_assumption_count' => $r['manual_assumption_count'], 'totals' => $r['totals'],
                'sources' => $sources, ...$this->page($rows, $v),
            ];
        });
    }

    private function budgetActuals(User $user, array $a): array
    {
        $v = Validator::make($a, ['budget_version_id' => ['required', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:100'], 'offset' => ['sometimes', 'integer', 'min:0', 'max:1000']])->validate();

        return $this->withPrincipalContext($user, function () use ($v): array {
            $version = BudgetVersion::query()->whereKey($v['budget_version_id'])->whereIn('status', [BudgetVersion::STATUS_APPROVED, BudgetVersion::STATUS_RETIRED])->first();
            if (! $version) {
                return ['availability' => 'unavailable_budget_version_not_approved'];
            }
            $r = app(BudgetActualsComparisonService::class)->compare($version, persistSnapshot: false, includeSourceIds: false);
            $rows = array_map(fn (array $x): array => Arr::except($x, ['source_transaction_ids', 'dimensions']), $r['rows']);
            $reasons = collect($r['excluded'])->countBy('reason')->map(fn (int $n, string $reason): array => ['reason_code' => $this->safeCode($reason), 'count' => $n])->values()->all();

            return ['availability' => 'available', 'timezone' => self::TZ, 'generated_at' => $r['generated_at'], 'budget_version' => $r['budget_version'],
                'period_start' => $r['period_start'], 'period_end' => $r['period_end'], 'currency_code' => $r['currency_code'],
                'actuals_basis' => $r['actuals_basis'], 'actuals_basis_label' => $r['actuals_basis_label'],
                'excluded_count' => count($r['excluded']), 'excluded_known_amount' => $r['excluded_known_amount'],
                'excluded_known_amount_count' => $r['excluded_known_amount_count'], 'excluded_reason_counts' => $reasons,
                ...$this->page($rows, $v)];
        });
    }

    private function profitability(User $user, array $a): array
    {
        $v = Validator::make($a, ['run_id' => ['sometimes', 'integer', 'min:1'], 'currency_code' => ['sometimes', 'regex:/^[A-Z]{3}$/'], 'product_id' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:100'], 'offset' => ['sometimes', 'integer', 'min:0', 'max:1000']])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            $query = ProfitabilityCalculationRun::query()->where('status', 'completed');
            if (isset($v['run_id'])) {
                $query->whereKey($v['run_id']);
            }
            $run = $query->orderByDesc('created_at')->first();
            if (! $run) {
                return ['availability' => 'unavailable_completed_run_not_found'];
            }
            $products = $this->has($user, 'product.index');
            $r = app(ProfitabilityReportService::class)->report($run, ['currency_code' => $v['currency_code'] ?? null, 'product_id' => $products ? ($v['product_id'] ?? null) : null], false);
            $rows = $products ? collect($r['results'])->map(fn ($x): array => [
                'product_title' => (string) $x->product?->title, 'currency_code' => $x->currency_code,
                'revenue_amount' => $x->revenue_amount, 'direct_cost_amount' => $x->direct_cost_amount,
                'overhead_cost_amount' => $x->overhead_cost_amount, 'provisional_profit_amount' => $x->provisional_profit_amount,
                'provisional_margin_percent' => $x->provisional_margin_percent,
            ])->values()->all() : [];
            $totals = [];
            foreach ($r['currency_totals'] as $currency => $x) {
                $totals[] = [
                    'currency_code' => $currency, 'revenue_amount' => $x['revenue_amount'], 'direct_cost_amount' => $x['direct_cost_amount'],
                    'overhead_cost_amount' => $x['overhead_cost_amount'], 'provisional_profit_amount' => $x['provisional_profit_amount'],
                    'provisional_margin_percent' => $x['provisional_margin_percent'], 'result_count' => $products ? $x['result_count'] : null, 'complete' => $x['complete'],
                ];
            }
            $exceptions = collect($run->exceptions ?? [])->map(fn (array $x): string => $this->safeCode($x['code'] ?? 'unknown_exception'))->countBy()
                ->map(fn (int $n, string $code): array => ['code' => $code, 'count' => $n])->values()->all();

            return ['availability' => 'available', 'timezone' => self::TZ, 'run_status' => $run->status, 'formula_version' => $run->formula_version,
                'period_start' => (string) $run->period_start, 'period_end' => (string) $run->period_end,
                'created_at' => $run->created_at, 'result_count' => $products ? count($rows) : null,
                'results_availability' => $products ? 'available' : 'unavailable_permission_denied',
                'currency_totals' => $totals, 'exception_count' => count($run->exceptions ?? []), 'exception_code_counts' => $exceptions,
                'customer_details_available' => false, 'project_dimension_available' => false, 'project_dimension_reason' => 'project_attribution_unavailable',
                ...$this->page($rows, $v, 'results')];
        });
    }

    private function reconciliation(User $user, array $a): array
    {
        $v = Validator::make($a, [
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'account_id' => ['sometimes', 'integer', 'min:1'], 'currency_code' => ['sometimes', 'regex:/^[A-Z]{3}$/'],
            'decision' => ['sometimes', Rule::in(['unreviewed', 'exact_match', 'partial_candidate', 'unmatched', 'ambiguous', 'rejected'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'], 'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            $from = CarbonImmutable::parse($v['start_date'], self::TZ);
            $to = CarbonImmutable::parse($v['end_date'], self::TZ);
            if ($from->diffInDays($to) > 90) {
                return ['availability' => 'unavailable_date_range_exceeds_limit', 'timezone' => self::TZ, 'from' => $v['start_date'], 'to' => $v['end_date'], 'maximum_days' => 90, 'total_count' => null, 'returned_count' => 0, 'rows' => []];
            }
            $tx = $this->has($user, 'transaction.index');
            $accounts = $this->has($user, 'financial_account.index');
            if (isset($v['account_id']) && ! $accounts) {
                return ['availability' => 'unavailable_permission_denied', 'rows' => []];
            }
            $r = app(FinancialReconciliationReportData::class)->report($from, $to, $accounts ? ($v['account_id'] ?? null) : null, $v['currency_code'] ?? null, $v['decision'] ?? null);
            $rows = array_map(fn (array $x): array => [
                'date' => $x['date'], 'direction' => $x['direction'], 'amount' => $x['amount'], 'currency_code' => $x['currency'],
                'account' => $accounts ? $x['account'] : null, 'status' => $tx ? $x['status'] : 'unavailable_permission_denied',
                'review_reasons' => $tx ? $x['review_reasons'] : [], 'candidate_status' => $tx ? $x['candidate_status'] : 'unavailable_permission_denied',
                'candidate_count' => $tx ? $x['candidate_count'] : null,
                'candidates' => $tx ? array_map(fn (array $c): array => [
                    'date' => $c['date'], 'amount' => $c['amount'], 'currency_code' => $c['currency'], 'direction' => $c['direction'],
                    'match_type' => $c['match_type'], 'allocation_counts' => $c['allocation_counts'],
                    'shared_candidate_count' => $c['shared_candidate_count'] ?? null,
                ], $x['candidates']) : [],
            ], $r['rows']);
            $summary = $r['summary'];
            if (! $tx) {
                foreach (['exact_match', 'partial_candidate', 'unmatched', 'ambiguous', 'rejected', 'unreviewed', 'exact_candidates', 'near_candidates', 'partial_candidates'] as $k) {
                    $summary[$k] = null;
                }
            }
            $totals = array_map(fn (array $x): array => [
                'currency_code' => $x['currency'], 'direction' => $x['direction'], 'statement_rows' => $x['statement_rows'],
                'statement_amount' => $x['statement_amount'], 'exact_match_amount' => $tx ? $x['exact_match_amount'] : null,
                'partial_candidate_amount' => $tx ? $x['partial_candidate_amount'] : null, 'unmatched_amount' => $tx ? $x['unmatched_amount'] : null,
                'ambiguous_amount' => $tx ? $x['ambiguous_amount'] : null, 'unreviewed_amount' => $tx ? $x['unreviewed_amount'] : null,
                'rejected_amount' => $tx ? $x['rejected_amount'] : null,
            ], $r['totals_by_currency']);

            return ['availability' => 'available', 'timezone' => self::TZ, 'from' => $r['from'], 'to' => $r['to'],
                'generated_at' => $r['generated_at'], 'maximum_days' => 90, 'summary' => $summary, 'totals_by_currency' => $totals,
                'currencies' => $r['currencies'], 'accounts' => $accounts ? array_map(fn (array $x): array => ['title' => $x['title']], $r['accounts']) : [],
                'account_data_availability' => $accounts ? 'available' : 'unavailable_permission_denied',
                'candidate_data_availability' => $tx ? 'available' : 'unavailable_permission_denied',
                ...$this->page($rows, $v)];
        });
    }

    private function invoice(User $user, array $a): array
    {
        $v = Validator::make($a, [
            'invoice_kind' => ['required', Rule::in(['issued_customer_invoice', 'legacy_customer_invoice', 'vendor_invoice'])],
            'source_id' => ['required', 'integer', 'min:1'], 'as_of' => ['sometimes', 'date_format:Y-m-d'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            $permission = $v['invoice_kind'] === 'vendor_invoice' ? 'vendor_invoice.index' : 'customer_invoice.index';
            if (! $this->has($user, $permission)) {
                return ['availability' => 'unavailable_permission_denied'];
            }
            $r = app(ReceivablesPayablesData::class)->report(CarbonImmutable::parse($v['as_of'] ?? 'today', self::TZ));
            $source = $v['invoice_kind'] === 'vendor_invoice' ? $r['payables'] : $r['receivables'];
            $row = collect($source)->first(fn (array $x): bool => (int) ($x['source_id'] ?? 0) === (int) $v['source_id'] && $x['source_type'] === $v['invoice_kind']);
            if (! $row) {
                return ['availability' => 'unavailable_invoice_not_found'];
            }

            return ['availability' => 'available', 'as_of' => $r['as_of'], 'timezone' => self::TZ, 'invoice' => $this->safeInvoice($row, $this->has($user, 'transaction.index'))];
        });
    }

    private function safeCode(mixed $value): string
    {
        $code = preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim((string) $value))) ?: 'unknown';

        return trim($code, '_') ?: 'unknown';
    }

    private function schema(string $name): array
    {
        $props = match ($name) {
            'get_transactions' => ['start_date' => ['type' => 'string', 'format' => 'date'], 'end_date' => ['type' => 'string', 'format' => 'date'], 'currency_code' => ['type' => 'string'], 'direction' => ['type' => 'string'], 'category' => ['type' => 'string'], 'account_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
            'get_receivables', 'get_payables' => ['as_of' => ['type' => 'string', 'format' => 'date'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
            'get_cash_forecast' => ['scenario_id' => ['type' => 'integer'], 'as_of' => ['type' => 'string', 'format' => 'date'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
            'get_budget_vs_actuals' => ['budget_version_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
            'get_profitability_report' => ['run_id' => ['type' => 'integer'], 'currency_code' => ['type' => 'string'], 'product_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
            'get_reconciliation_status' => ['start_date' => ['type' => 'string', 'format' => 'date'], 'end_date' => ['type' => 'string', 'format' => 'date'], 'account_id' => ['type' => 'integer'], 'currency_code' => ['type' => 'string'], 'decision' => ['type' => 'string'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']],
            'get_invoice_details' => ['invoice_kind' => ['type' => 'string', 'enum' => ['issued_customer_invoice', 'legacy_customer_invoice', 'vendor_invoice']], 'source_id' => ['type' => 'integer'], 'as_of' => ['type' => 'string', 'format' => 'date']],
            default => [],
        };
        $required = match ($name) {
            'get_transactions', 'get_reconciliation_status' => ['start_date', 'end_date'],
            'get_cash_forecast' => ['scenario_id'], 'get_budget_vs_actuals' => ['budget_version_id'],
            'get_invoice_details' => ['invoice_kind', 'source_id'], default => [],
        };

        return ['type' => 'object', 'properties' => (object) $props, 'required' => $required, 'additionalProperties' => false];
    }
}
