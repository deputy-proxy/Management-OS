<?php

namespace App\Mcp\Tools\Crm;

use App\Mcp\Authorization\AuthorizedMcpCapabilityRegistrar;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerInteraction;
use App\Models\CustomerLead;
use App\Models\CustomerLeadAttributionEvent;
use App\Models\CustomerLeadStatusHistory;
use App\Models\CustomerUnitEconomicsResult;
use App\Models\CustomerUnitEconomicsRun;
use App\Models\User;
use App\Services\CustomerDashboardData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Mcp\Server\Builder as McpBuilder;

final class CrmMcpCapabilityRegistrar extends AuthorizedMcpCapabilityRegistrar
{
    private const TZ = 'Europe/Bucharest';

    public function register(McpBuilder $builder): void
    {
        $tools = [
            'search_customers' => fn (User $user, array $a): array => $this->searchCustomers($user, $a),
            'get_customer_details' => fn (User $user, array $a): array => $this->customerDetails($user, $a),
            'get_customer_contracts' => fn (User $user, array $a): array => $this->customerContracts($user, $a),
            'get_customer_interactions' => fn (User $user, array $a): array => $this->customerInteractions($user, $a),
            'get_sales_pipeline' => fn (User $user, array $a): array => $this->salesPipeline($user),
            'get_lead_details' => fn (User $user, array $a): array => $this->leadDetails($user, $a),
            'get_customer_economics' => fn (User $user, array $a): array => $this->customerEconomics($user, $a),
        ];
        $descriptions = [
            'search_customers' => 'Searches customers by name with bounded pagination. Contact details and government identifiers are excluded.',
            'get_customer_details' => 'Returns a minimal customer profile without contact details, address, government identifiers or free-text notes.',
            'get_customer_contracts' => 'Returns contract dates and signature metadata for one customer. Documents and customer identity details are excluded.',
            'get_customer_interactions' => 'Returns interaction timestamps and ratings. Free-text descriptions and initiator details are excluded.',
            'get_sales_pipeline' => 'Returns aggregate open-lead status and source-attribution counts with reporting date and attribution caveats.',
            'get_lead_details' => 'Returns a limited lead profile, status history and attribution history. Email, phone, personal interests and converted customer identifiers are excluded.',
            'get_customer_economics' => 'Reads the latest immutable customer unit-economics run. Source snapshots and customer identifiers are excluded.',
        ];

        foreach ($tools as $name => $handler) {
            $this->addAuthorizedTool($builder, $name, $handler, $descriptions[$name], $this->schema($name));
        }
    }

    private function searchCustomers(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'customer_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sort_by' => ['sometimes', Rule::in(['name', 'created_at', 'updated_at'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Builder<Customer> $query */
            $query = $this->scopeQuery($user, 'search_customers', Customer::query())
                ->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$this->escapeLike((string) $v['query']).'%']);

            if (filled($v['customer_type'] ?? null)) {
                $query->where('customer_type', $v['customer_type']);
            }

            $query->select(['id', 'name', 'customer_type', 'created_at'])
                ->orderBy($v['sort_by'] ?? 'name', $v['sort_direction'] ?? 'asc')
                ->orderBy('id');

            return $this->pageQuery($query, $v, 'customers', fn (Customer $customer): array => [
                'id' => (int) $customer->getKey(),
                'name' => (string) $customer->name,
                'customer_type' => (string) $customer->customer_type,
                'created_at' => $customer->created_at?->toIso8601String(),
            ]);
        });
    }

    private function customerDetails(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, ['customer_id' => ['required', 'integer', 'min:1']])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Customer|null $customer */
            $customer = $this->scopeQuery($user, 'get_customer_details', Customer::query())->whereKey($v['customer_id'])->first();
            if (! $customer) {
                return ['availability' => 'unavailable_customer_not_found'];
            }
            $this->authorizeRecord($user, 'get_customer_details', $customer);

            return ['availability' => 'available', 'customer' => [
                'id' => (int) $customer->getKey(),
                'name' => (string) $customer->name,
                'customer_type' => (string) $customer->customer_type,
                'created_at' => $customer->created_at?->toIso8601String(),
                'updated_at' => $customer->updated_at?->toIso8601String(),
            ]];
        });
    }

    private function customerContracts(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'customer_id' => ['required', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Customer|null $customer */
            $customer = $this->scopeQuery($user, 'get_customer_contracts', Customer::query())->whereKey($v['customer_id'])->first();
            if (! $customer) {
                return ['availability' => 'unavailable_customer_not_found'];
            }
            $this->authorizeRecord($user, 'get_customer_contracts', $customer);

            /** @var Builder<CustomerContract> $query */
            $query = $this->scopeQuery($user, 'get_customer_contracts', CustomerContract::query())
                ->where('customer_id', $customer->getKey())
                ->orderByDesc('start_date')->orderByDesc('id')
                ->select(['id', 'contract_code', 'start_date', 'end_date', 'signed_at']);

            return ['availability' => 'available', ...$this->pageQuery($query, $v, 'contracts', fn (CustomerContract $contract): array => [
                'contract_code' => (string) $contract->contract_code,
                'start_date' => CarbonImmutable::parse($contract->getAttribute('start_date'), self::TZ)->toDateString(),
                'end_date' => CarbonImmutable::parse($contract->getAttribute('end_date'), self::TZ)->toDateString(),
                'signed_at' => $contract->getAttribute('signed_at') ? CarbonImmutable::parse($contract->getAttribute('signed_at'), self::TZ)->toIso8601String() : null,
            ])];
        });
    }

    private function customerInteractions(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'customer_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Customer|null $customer */
            $customer = $this->scopeQuery($user, 'get_customer_interactions', Customer::query())->whereKey($v['customer_id'])->first();
            if (! $customer) {
                return ['availability' => 'unavailable_customer_not_found'];
            }
            $this->authorizeRecord($user, 'get_customer_interactions', $customer);

            /** @var Builder<CustomerInteraction> $query */
            $query = $this->scopeQuery($user, 'get_customer_interactions', CustomerInteraction::query())
                ->where('customer_id', $customer->getKey());
            if (isset($v['start_date'])) {
                $query->whereDate('timestamp', '>=', $v['start_date']);
            }
            if (isset($v['end_date'])) {
                $query->whereDate('timestamp', '<=', $v['end_date']);
            }

            $query->orderByDesc('timestamp')->orderByDesc('id')
                ->select(['id', 'timestamp', 'interaction_rating']);

            return ['availability' => 'available', ...$this->pageQuery($query, $v, 'interactions', fn (CustomerInteraction $interaction): array => [
                'timestamp' => $interaction->timestamp ? CarbonImmutable::parse($interaction->timestamp, self::TZ)->toIso8601String() : null,
                'interaction_rating' => $interaction->interaction_rating,
            ])];
        });
    }

    private function salesPipeline(User $user): array
    {
        return $this->withPrincipalContext($user, function () use ($user): array {
            $now = CarbonImmutable::now(self::TZ);
            /** @var Builder<CustomerLead> $leadQuery */
            $leadQuery = $this->scopeQuery($user, 'get_sales_pipeline', CustomerLead::query());
            $summary = app(CustomerDashboardData::class)->salesPipeline($leadQuery, $now);
            $attribution = $summary['attribution'];

            return [
                'availability' => 'available',
                'as_of' => $summary['as_of'],
                'timezone' => $summary['timezone'],
                'open_lead_count' => $summary['open_lead_count'],
                'lead_statuses' => $summary['lead_statuses'],
                'attribution' => [
                    ...$attribution,
                    'basis' => 'Open-lead status counts exclude converted leads; attribution counts include all non-soft-deleted leads with recorded source fields.',
                ],
            ];
        });
    }

    private function leadDetails(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, ['lead_id' => ['required', 'integer', 'min:1']])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var CustomerLead|null $lead */
            $lead = $this->scopeQuery($user, 'get_lead_details', CustomerLead::query())
                ->withCount(['statusHistory', 'attributionEvents'])
                ->whereKey($v['lead_id'])->first();
            if (! $lead) {
                return ['availability' => 'unavailable_lead_not_found'];
            }
            $this->authorizeRecord($user, 'get_lead_details', $lead);

            /** @var Collection<int, CustomerLeadAttributionEvent> $attributionEvents */
            $attributionEvents = $lead->attributionEvents()->limit(100)->get();
            /** @var Collection<int, CustomerLeadStatusHistory> $statusHistory */
            $statusHistory = $lead->statusHistory()->limit(100)->get();

            $attribution = $attributionEvents->map(function (CustomerLeadAttributionEvent $event): array {
                $rawSnapshot = $event->getAttribute('snapshot');
                $snapshot = is_array($rawSnapshot) ? $rawSnapshot : [];
                $conversion = is_array($snapshot['conversion'] ?? null) ? $snapshot['conversion'] : [];
                $firstTouch = is_array($snapshot['first_touch'] ?? null) ? $snapshot['first_touch'] : [];
                $lastTouch = is_array($snapshot['last_touch'] ?? null) ? $snapshot['last_touch'] : $snapshot;

                return [
                    'event_type' => (string) $event->getAttribute('event_type'),
                    'occurred_at' => CarbonImmutable::parse($event->getAttribute('occurred_at'), self::TZ)->toIso8601String(),
                    'source_category' => $lastTouch['source_category'] ?? null,
                    'campaign_name' => $lastTouch['campaign_name'] ?? null,
                    'referral_partner' => $lastTouch['referral_partner'] ?? null,
                    'attribution_policy_version' => $lastTouch['attribution_policy_version'] ?? null,
                    'source_captured_at' => $lastTouch['source_captured_at'] ?? null,
                    'first_touch_source_category' => $firstTouch['source_category'] ?? null,
                    'first_touch_campaign_name' => $firstTouch['campaign_name'] ?? null,
                    'converted_at' => $conversion['converted_at'] ?? null,
                ];
            })->values()->all();

            return [
                'availability' => 'available',
                'lead' => [
                    'id' => (int) $lead->getKey(),
                    'full_name' => (string) $lead->full_name,
                    'status' => (string) $lead->status,
                    'source_category' => (string) $lead->source_category,
                    'campaign_name' => $lead->campaign_name,
                    'referral_partner' => $lead->referral_partner,
                    'created_at' => CarbonImmutable::parse($lead->getAttribute('created_at'), self::TZ)->toIso8601String(),
                    'last_contacted_at' => $lead->getAttribute('last_contacted_at') ? CarbonImmutable::parse($lead->getAttribute('last_contacted_at'), self::TZ)->toIso8601String() : null,
                ],
                'status_history_count' => (int) $lead->status_history_count,
                'attribution_history_count' => (int) $lead->attribution_events_count,
                'history_truncated' => $lead->status_history_count > 100 || $lead->attribution_events_count > 100,
                'status_history' => $statusHistory->map(fn (CustomerLeadStatusHistory $event): array => [
                    'from_status' => $event->getAttribute('from_status'),
                    'to_status' => $event->getAttribute('to_status'),
                    'changed_at' => CarbonImmutable::parse($event->getAttribute('changed_at'), self::TZ)->toIso8601String(),
                ])->values()->all(),
                'attribution_history' => $attribution,
            ];
        });
    }

    private function customerEconomics(User $user, array $arguments): array
    {
        $v = Validator::make($arguments, [
            'run_id' => ['sometimes', 'uuid'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ])->validate();

        return $this->withPrincipalContext($user, function () use ($user, $v): array {
            /** @var Builder<CustomerUnitEconomicsRun> $query */
            $query = $this->scopeQuery($user, 'get_customer_economics', CustomerUnitEconomicsRun::query());
            if (isset($v['run_id'])) {
                $query->where('run_id', $v['run_id']);
            }
            $run = $query->orderByDesc('id')->first();
            if (! $run) {
                return ['availability' => 'unavailable_completed_run_not_found'];
            }

            $rawExceptions = $run->getAttribute('exceptions');
            $exceptions = is_array($rawExceptions) ? $rawExceptions : [];
            $exceptionCounts = collect($exceptions)
                ->map(fn (array $exception): string => $this->safeCode($exception['type'] ?? 'unknown'))
                ->countBy()
                ->map(fn (int $count, string $code): array => ['code' => $code, 'count' => $count])
                ->values()->all();
            /** @var Builder<CustomerUnitEconomicsResult> $query */
            $query = $this->scopeQuery($user, 'get_customer_economics', CustomerUnitEconomicsResult::query())
                ->where('customer_unit_economics_run_id', $run->getKey())
                ->orderBy('source_category')->orderBy('currency_code');

            return [
                'availability' => 'available',
                'run_id' => (string) $run->getAttribute('run_id'),
                'period_start' => CarbonImmutable::parse($run->getAttribute('period_start'), self::TZ)->toDateString(),
                'period_end' => CarbonImmutable::parse($run->getAttribute('period_end'), self::TZ)->toDateString(),
                'policy_version' => $run->getAttribute('policy_version'),
                'created_at' => CarbonImmutable::parse($run->getAttribute('created_at'), self::TZ)->toIso8601String(),
                'exception_count' => count($exceptions),
                'exception_code_counts' => $exceptionCounts,
                ...$this->pageQuery($query, $v, 'results', fn (CustomerUnitEconomicsResult $result): array => [
                    'source_category' => $result->source_category,
                    'currency_code' => $result->currency_code,
                    'acquired_customer_count' => (int) $result->acquired_customer_count,
                    'cost_entry_count' => (int) $result->cost_entry_count,
                    'acquisition_cost_amount' => $result->acquisition_cost_amount,
                    'cac_amount' => $result->cac_amount,
                    'revenue_amount' => $result->revenue_amount,
                    'ltv_amount' => $result->ltv_amount,
                    'revenue_coverage_count' => (int) $result->revenue_coverage_count,
                    'revenue_unknown_count' => (int) $result->revenue_unknown_count,
                    'retention_coverage_count' => (int) $result->retention_coverage_count,
                    'retention_unknown_count' => (int) $result->retention_unknown_count,
                    'result_status' => $result->result_status,
                ]),
            ];
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function pageQuery(Builder $query, array $v, string $key, callable $map): array
    {
        $limit = (int) ($v['limit'] ?? 50);
        $offset = (int) ($v['offset'] ?? 0);
        $total = (clone $query)->count();
        $items = $query->limit($limit)->offset($offset)->get()->map($map)->values()->all();

        return [
            'total_count' => $total,
            'returned_count' => count($items),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $total > $offset + $limit,
            $key => $items,
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private function safeCode(mixed $value): string
    {
        $code = preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim((string) $value))) ?: 'unknown';

        return trim($code, '_') ?: 'unknown';
    }

    private function schema(string $name): array
    {
        $properties = match ($name) {
            'search_customers' => [
                'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                'customer_type' => ['type' => 'string', 'maxLength' => 50],
                'sort_by' => ['type' => 'string', 'enum' => ['name', 'created_at', 'updated_at']],
                'sort_direction' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            'get_customer_details' => ['customer_id' => ['type' => 'integer', 'minimum' => 1]],
            'get_customer_contracts' => [
                'customer_id' => ['type' => 'integer', 'minimum' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            'get_customer_interactions' => [
                'customer_id' => ['type' => 'integer', 'minimum' => 1],
                'start_date' => ['type' => 'string', 'format' => 'date'],
                'end_date' => ['type' => 'string', 'format' => 'date'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            'get_lead_details' => ['lead_id' => ['type' => 'integer', 'minimum' => 1]],
            'get_customer_economics' => [
                'run_id' => ['type' => 'string', 'format' => 'uuid'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
            ],
            default => [],
        };
        $required = match ($name) {
            'search_customers' => ['query'],
            'get_customer_details', 'get_customer_contracts', 'get_customer_interactions' => ['customer_id'],
            'get_lead_details' => ['lead_id'],
            default => [],
        };

        return [
            'type' => 'object',
            'properties' => (object) $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
