<?php

namespace App\Services;

use App\Models\ProductRevenue;
use App\Models\ProfitabilityCalculationResult;
use App\Models\ProfitabilityCalculationRun;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ProfitabilityReportService
{
    /**
     * Build a read-only report from one immutable calculation run.
     *
     * @param  array{product_id?: int|null, currency_code?: string|null, customer_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function report(
        ProfitabilityCalculationRun $run,
        array $filters = [],
        bool $includeCustomerDetails = false,
    ): array {
        /** @var EloquentCollection<int, ProfitabilityCalculationResult> $allResults */
        $allResults = $run->results()
            ->with('product')
            ->orderBy('product_id')
            ->orderBy('currency_code')
            ->get();

        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        $currencyCode = isset($filters['currency_code']) && $filters['currency_code'] !== ''
            ? strtoupper((string) $filters['currency_code'])
            : null;
        $customerId = isset($filters['customer_id']) ? (int) $filters['customer_id'] : null;

        $results = $allResults
            ->filter(function (ProfitabilityCalculationResult $result) use ($productId, $currencyCode): bool {
                return ($productId === null || (int) $result->product_id === $productId)
                    && ($currencyCode === null || $result->currency_code === $currencyCode);
            })
            ->values();

        /** @var Collection<string, EloquentCollection<int, ProfitabilityCalculationResult>> $groupedResults */
        $groupedResults = $results->groupBy('currency_code');
        $currencyTotals = $groupedResults
            ->map(fn (EloquentCollection $currencyResults): array => $this->currencyTotals($currencyResults))
            ->all();

        $customerReport = $includeCustomerDetails
            ? $this->customerBreakdown($results, $customerId)
            : [
                'customers' => [],
                'unattributed' => [],
                'reconciliation_issues' => [],
            ];

        $exceptions = collect($run->exceptions)
            ->filter(function (array $exception) use ($productId, $currencyCode): bool {
                $matchesProduct = $productId === null
                    || ! isset($exception['product_id'])
                    || (int) $exception['product_id'] === $productId;
                $matchesCurrency = $currencyCode === null
                    || ! isset($exception['currency'])
                    || strtoupper((string) $exception['currency']) === $currencyCode;

                return $matchesProduct && $matchesCurrency;
            })
            ->values();

        return [
            'results' => $results,
            'available_products' => $allResults
                ->map(fn (ProfitabilityCalculationResult $result): array => [
                    'id' => (int) $result->product_id,
                    'label' => (string) ($result->product?->getAttribute('title') ?? 'Deleted / unavailable product #'.$result->product_id),
                ])
                ->unique('id')
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
            'available_currencies' => $allResults->pluck('currency_code')->unique()->sort()->values(),
            'currency_totals' => $currencyTotals,
            'customers' => $customerReport['customers'],
            'unattributed' => $customerReport['unattributed'],
            'customer_reconciliation_issues' => $customerReport['reconciliation_issues'],
            'exceptions' => $exceptions,
            'project_dimension' => [
                'available' => false,
                'reason' => 'The calculation contract contains no explicit project attribution. Project values are not inferred from products, invoices, or other relationships.',
            ],
            'customer_details_available' => $includeCustomerDetails,
        ];
    }

    /**
     * @param  Collection<int, ProfitabilityCalculationResult>  $results
     * @return array<string, mixed>
     */
    private function currencyTotals(Collection $results): array
    {
        $revenue = $this->sumKnown($results, 'revenue_amount');
        $direct = $this->sumKnown($results, 'direct_cost_amount');
        $overhead = $this->sumKnown($results, 'overhead_cost_amount');
        $complete = $results->every(fn (ProfitabilityCalculationResult $result): bool => $result->result_status === 'provisional_complete'
            && $result->provisional_profit_amount !== null
        );
        $profit = $complete ? $this->sumKnown($results, 'provisional_profit_amount') : null;
        $margin = null;

        if ($profit !== null && $revenue !== null && ! BigDecimal::of($revenue)->isZero()) {
            $margin = BigDecimal::of($profit)
                ->multipliedBy(100)
                ->dividedBy(BigDecimal::of($revenue), 2, RoundingMode::HALF_UP)
                ->__toString();
        }

        return [
            'revenue_amount' => $revenue,
            'direct_cost_amount' => $direct,
            'overhead_cost_amount' => $overhead,
            'provisional_profit_amount' => $profit,
            'provisional_margin_percent' => $margin,
            'result_count' => (string) $results->count(),
            'complete' => $complete,
        ];
    }

    /**
     * @param  Collection<int, ProfitabilityCalculationResult>  $results
     * @return array{customers: array<int, array<string, mixed>>, unattributed: array<int, array<string, mixed>>, reconciliation_issues: array<int, array<string, mixed>>}
     */
    private function customerBreakdown(Collection $results, ?int $customerFilter): array
    {
        $revenueIds = $results
            ->flatMap(fn (ProfitabilityCalculationResult $result): array => $this->revenueIds($result))
            ->unique()
            ->values();

        $revenues = ProductRevenue::withTrashed()
            ->with([
                'customerInvoice' => fn ($relation) => $relation->getQuery()->withTrashed(),
                'customerInvoice.customer' => fn ($relation) => $relation->getQuery()->withTrashed(),
            ])
            ->whereKey($revenueIds)
            ->get()
            ->keyBy('id');

        /** @var array<string, array<string, mixed>> $customers */
        $customers = [];
        /** @var array<string, array<string, mixed>> $unattributed */
        $unattributed = [];
        $issues = [];

        foreach ($results as $result) {
            $ids = $this->revenueIds($result);
            if ($ids === [] || $result->revenue_amount === null) {
                continue;
            }

            $sourceRows = [];
            $actualTotal = BigDecimal::zero();
            $sourceIsComplete = true;

            foreach ($ids as $revenueId) {
                /** @var ProductRevenue|null $revenue */
                $revenue = $revenues->get($revenueId);
                if (! $revenue) {
                    $sourceIsComplete = false;
                    $issues[] = [
                        'result_id' => $result->getKey(),
                        'product_id' => $result->product_id,
                        'currency' => $result->currency_code,
                        'reason' => 'A revenue source row referenced by the immutable run is no longer available.',
                        'source_id' => $revenueId,
                    ];

                    continue;
                }

                $sourceCurrency = strtoupper((string) $revenue->currency_code);
                if ((int) $revenue->product_id !== (int) $result->product_id
                    || $sourceCurrency !== $result->currency_code
                    || $revenue->amount_decimal === null) {
                    $sourceIsComplete = false;
                    $issues[] = [
                        'result_id' => $result->getKey(),
                        'product_id' => $result->product_id,
                        'currency' => $result->currency_code,
                        'reason' => 'A revenue source row no longer matches the product, currency, or canonical amount stored by the run.',
                        'source_id' => $revenueId,
                    ];

                    continue;
                }

                $actualTotal = $actualTotal->plus((string) $revenue->amount_decimal);
                $sourceRows[] = $revenue;
            }

            $expectedTotal = BigDecimal::of((string) $result->revenue_amount);
            if (! $sourceIsComplete || count($sourceRows) !== count($ids) || $actualTotal->compareTo($expectedTotal) !== 0) {
                if ($sourceIsComplete && $actualTotal->compareTo($expectedTotal) !== 0) {
                    $issues[] = [
                        'result_id' => $result->getKey(),
                        'product_id' => $result->product_id,
                        'currency' => $result->currency_code,
                        'reason' => 'Current revenue source amounts no longer reconcile to the immutable calculation result; customer amounts are withheld.',
                        'expected_amount' => $expectedTotal->toScale(2, RoundingMode::HALF_UP)->__toString(),
                        'current_source_amount' => $actualTotal->toScale(2, RoundingMode::HALF_UP)->__toString(),
                    ];
                }

                continue;
            }

            foreach ($sourceRows as $revenue) {
                $invoice = $revenue->customerInvoice;
                $customer = $invoice?->customer;
                $amount = BigDecimal::of((string) $revenue->amount_decimal);

                if (! $customer) {
                    $key = $result->currency_code;
                    $unattributed[$key] ??= [
                        'currency' => $result->currency_code,
                        'revenue_amount' => BigDecimal::zero(),
                        'revenue_ids' => [],
                        'invoice_ids' => [],
                        'reason' => 'No customer is available through ProductRevenue → CustomerInvoice → Customer.',
                    ];
                    $unattributed[$key]['revenue_amount'] = $unattributed[$key]['revenue_amount']->plus($amount);
                    $unattributed[$key]['revenue_ids'][] = (int) $revenue->getKey();
                    if ($invoice) {
                        $unattributed[$key]['invoice_ids'][] = (int) $invoice->getKey();
                    }

                    continue;
                }

                $key = $customer->getKey().'|'.$result->currency_code;
                $customers[$key] ??= [
                    'customer_id' => (int) $customer->getKey(),
                    'customer_name' => (string) $customer->name,
                    'currency' => $result->currency_code,
                    'revenue_amount' => BigDecimal::zero(),
                    'revenue_ids' => [],
                    'invoice_ids' => [],
                    'product_ids' => [],
                    'direct_cost_amount' => null,
                    'overhead_cost_amount' => null,
                    'provisional_profit_amount' => null,
                    'provisional_margin_percent' => null,
                ];
                $customers[$key]['revenue_amount'] = $customers[$key]['revenue_amount']->plus($amount);
                $customers[$key]['revenue_ids'][] = (int) $revenue->getKey();
                $customers[$key]['invoice_ids'][] = (int) $invoice->getKey();
                $customers[$key]['product_ids'][] = (int) $revenue->product_id;
            }
        }

        $customerRows = collect($customers)
            ->filter(fn (array $customer): bool => $customerFilter === null || $customer['customer_id'] === $customerFilter)
            ->map(fn (array $customer): array => [
                ...$customer,
                'revenue_amount' => $customer['revenue_amount']->toScale(2, RoundingMode::HALF_UP)->__toString(),
                'revenue_ids' => array_values(array_unique($customer['revenue_ids'])),
                'invoice_ids' => array_values(array_unique($customer['invoice_ids'])),
                'product_ids' => array_values(array_unique($customer['product_ids'])),
            ])
            ->values()
            ->all();

        $unattributedRows = collect($unattributed)
            ->map(fn (array $item): array => [
                ...$item,
                'revenue_amount' => $item['revenue_amount']->toScale(2, RoundingMode::HALF_UP)->__toString(),
                'revenue_ids' => array_values(array_unique($item['revenue_ids'])),
                'invoice_ids' => array_values(array_unique($item['invoice_ids'])),
            ])
            ->values()
            ->all();

        return [
            'customers' => $customerRows,
            'unattributed' => $unattributedRows,
            'reconciliation_issues' => $issues,
        ];
    }

    /** @return array<int, int> */
    private function revenueIds(ProfitabilityCalculationResult $result): array
    {
        $ids = $result->source_snapshot['revenue_ids'] ?? [];
        $ids = is_array($ids) ? $ids : [];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param  Collection<int, ProfitabilityCalculationResult>  $results
     */
    private function sumKnown(Collection $results, string $field): ?string
    {
        $sum = BigDecimal::zero();

        foreach ($results as $result) {
            $value = $result->getAttribute($field);
            if ($value === null) {
                return null;
            }

            $sum = $sum->plus((string) $value);
        }

        return $sum->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
