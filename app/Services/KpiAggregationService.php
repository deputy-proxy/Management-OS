<?php

namespace App\Services;

use App\Models\CompetitorOfferObservation;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\KpiDefinition;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class KpiAggregationService
{
    public const CALCULATION_TYPES = [
        'customer_record_count',
        'unconverted_lead_count',
        'unknown_lead_source_count',
        'open_task_count',
        'overdue_task_count',
        'competitor_offer_snapshot_count',
    ];

    public const MAX_PROVENANCE_RECORDS = 100;
    public const MAX_DIMENSION_GROUPS = 50;

    public function __construct(private readonly KpiCatalogueRegistry $catalogue) {}

    /**
     * Evaluate the allow-listed catalogue. An active, effective-dated database
     * definition overrides its static v1 definition without changing the key.
     *
     * @param array<string, bool> $permissions
     * @return array<string, mixed>
     */
    public function current(array $permissions = [], ?CarbonImmutable $asOf = null): array
    {
        $timezone = $this->catalogue->timezone();
        $asOf = ($asOf ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $date = $asOf->toDateString();
        $activeDefinitions = KpiDefinition::query()
            ->where('status', KpiDefinition::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->get()
            ->keyBy('key');

        $results = [];
        foreach ($this->catalogue->all() as $key => $definition) {
            $active = $activeDefinitions->get($key);
            $results[] = $active instanceof KpiDefinition
                ? $this->aggregate($active, $permissions, $asOf, $date)
                : $this->aggregateStatic($key, $definition, $permissions, $asOf, $date);
        }

        return [
            'catalogue_version' => $this->catalogue->version(),
            'catalogue_approval_status' => $this->catalogue->approvalStatus(),
            'as_of' => $date,
            'timezone' => $timezone,
            'results' => $results,
        ];
    }

    /** @param array<string, bool> $permissions
     * @return array<string, mixed>
     */
    public function aggregate(KpiDefinition $definition, array $permissions, ?CarbonImmutable $asOf = null, ?string $date = null): array
    {
        $timezone = $this->catalogue->timezone();
        $asOf = ($asOf ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $date ??= $asOf->toDateString();
        $contract = $this->catalogue->get($definition->key);

        if (! ($permissions[$definition->permission] ?? false)) {
            return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, null, 'unavailable_permission_denied', $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition, $contract);
        }

        if ($contract === null || ($contract['availability'] ?? null) !== 'available') {
            $reason = $contract['availability'] ?? 'unavailable_missing_calculation';

            return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, null, $reason, $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition, $contract);
        }

        $filters = $definition->getAttribute('filters') ?? [];
        $dimensions = $definition->getAttribute('dimensions') ?? [];
        $aggregation = $definition->getAttribute('aggregation') ?? 'count';
        $period = $definition->getAttribute('period') ?? ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false];
        if (! is_array($filters) || ! is_array($dimensions) || ! is_string($aggregation) || ! is_array($period)) {
            return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, null, 'unavailable_invalid_definition', $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition, $contract);
        }

        try {
            $configuration = app(KpiDefinitionConfiguration::class)->validate($definition->calculation_type, $filters, $dimensions, $aggregation, $period);
        } catch (ValidationException) {
            return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, null, 'unavailable_invalid_definition', $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition, $contract);
        }

        $evaluation = $this->evaluate(
            $definition->calculation_type,
            $permissions,
            $date,
            $configuration['filters'],
            $configuration['dimensions'],
        );

        return $this->result(
            $definition->key,
            $definition->label,
            $definition->domain,
            $definition->unit,
            $evaluation['value'],
            $evaluation['availability'],
            $definition->source,
            $definition->permission,
            $definition->description,
            $asOf,
            $definition->version,
            $definition,
            $contract,
            $evaluation['breakdown'],
            $evaluation,
        );
    }

    /** @param array<string, mixed> $definition
     * @param array<string, bool> $permissions
     * @return array<string, mixed>
     */
    private function aggregateStatic(string $key, array $definition, array $permissions, CarbonImmutable $asOf, string $date): array
    {
        if (($definition['availability'] ?? null) !== 'available') {
            $availability = $definition['availability'] ?? 'unavailable_missing_calculation';
            $evaluation = $this->emptyEvaluation($availability);
        } elseif (! ($permissions[$definition['permission']] ?? false)) {
            $availability = 'unavailable_permission_denied';
            $evaluation = $this->emptyEvaluation($availability);
        } else {
            $evaluation = $this->evaluate($definition['calculation_type'], $permissions, $date);
            $availability = $evaluation['availability'];
        }

        return $this->result(
            $key,
            $definition['label'],
            $definition['domain'],
            $definition['unit'],
            $evaluation['value'],
            $availability,
            $definition['source'],
            $definition['permission'],
            $definition['description'],
            $asOf,
            null,
            null,
            $definition,
            $evaluation['breakdown'],
            $evaluation,
        );
    }

    /**
     * Execute only registered query contracts. Counts are exact; source IDs and
     * grouped dimensions are deliberately capped to keep provenance bounded.
     *
     * @param array<string, bool> $permissions
     * @param array<int, array{field: string, operator: string, value: string|array<int, string>}> $filters
     * @param array<int, string> $dimensions
     * @return array<string, mixed>
     */
    private function evaluate(string $type, array $permissions, string $date, array $filters = [], array $dimensions = []): array
    {
        $baseQuery = $this->baseQuery($type, $permissions);
        if ($baseQuery === null) {
            return $this->emptyEvaluation('unavailable_viewer_context');
        }

        $query = clone $baseQuery;
        if (in_array($type, ['unconverted_lead_count', 'unknown_lead_source_count'], true)) {
            $query = $this->applyLeadFilters($query, $filters);
        }
        if ($type === 'overdue_task_count') {
            $query->whereDate('due_date', '<', $date);
        }

        $candidateCount = (clone $baseQuery)->count();
        $includedCount = (clone $query)->count();
        $recordIds = (clone $query)
            ->orderBy('id')
            ->limit(self::MAX_PROVENANCE_RECORDS)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [
            'value' => $includedCount,
            'availability' => 'available',
            'included_count' => $includedCount,
            'excluded_count' => max(0, $candidateCount - $includedCount),
            'candidate_count' => $candidateCount,
            'breakdown' => $this->dimensionBreakdown($query, $dimensions),
            'source_record_ids' => $recordIds,
            'source_record_ids_truncated' => $includedCount > self::MAX_PROVENANCE_RECORDS,
            'filters_applied' => $filters,
        ];
    }

    /** @param array<string, bool> $permissions */
    private function baseQuery(string $type, array $permissions): ?Builder
    {
        return match ($type) {
            'customer_record_count' => Customer::query(),
            'unconverted_lead_count' => CustomerLead::query()->whereNull('converted_customer_id'),
            'unknown_lead_source_count' => CustomerLead::query()->where('source_category', 'unknown'),
            'open_task_count', 'overdue_task_count' => $this->taskQuery($permissions),
            'competitor_offer_snapshot_count' => CompetitorOfferObservation::query(),
            default => null,
        };
    }

    /** @param array<string, bool> $permissions */
    private function taskQuery(array $permissions): ?Builder
    {
        $query = Task::query()->where('status', '!=', 'done');
        if ($permissions['task.view_all'] ?? false) {
            return $query;
        }

        $userId = auth()->id();
        if ($userId === null) {
            return null;
        }

        return $query->where(fn (Builder $scope) => $scope
            ->where('assigned_to', $userId)
            ->orWhere('created_by', $userId));
    }

    /**
     * Field names and operators are mapped explicitly. Stored JSON is validated
     * again at execution time so stale or tampered configuration cannot widen
     * the query surface.
     *
     * @param array<int, array{field: string, operator: string, value: string|array<int, string>}> $filters
     */
    private function applyLeadFilters(Builder $query, array $filters): Builder
    {
        $validated = app(KpiDefinitionConfiguration::class)->validate('unconverted_lead_count', $filters);
        foreach ($validated['filters'] as $filter) {
            $column = match ($filter['field']) {
                'status' => 'status',
                'source_category' => 'source_category',
                default => throw new \LogicException('Unregistered KPI filter field.'),
            };
            match ($filter['operator']) {
                'eq' => $query->where($column, '=', $filter['value']),
                'neq' => $query->where($column, '!=', $filter['value']),
                'in' => $query->whereIn($column, (array) $filter['value']),
                default => throw new \LogicException('Unregistered KPI filter operator.'),
            };
        }

        return $query;
    }

    /**
     * @param array<int, string> $dimensions
     * @return array<string, int>
     */
    private function dimensionBreakdown(Builder $query, array $dimensions): array
    {
        if ($dimensions === []) {
            return [];
        }

        // Dimension availability is checked before execution by the definition
        // configuration service. This method receives only registered columns.
        $rows = (clone $query)
            ->select($dimensions)
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy(...$dimensions)
            ->orderBy('aggregate')
            ->limit(self::MAX_DIMENSION_GROUPS)
            ->get();

        $breakdown = [];
        foreach ($rows as $row) {
            $label = collect($dimensions)
                ->map(fn (string $field): string => $field.'='.((string) ($row->getAttribute($field) ?? '(empty)')))
                ->implode(' / ');
            $breakdown[$label] = (int) $row->getAttribute('aggregate');
        }
        ksort($breakdown);

        return $breakdown;
    }

    /** @return array<string, mixed> */
    private function emptyEvaluation(string $availability): array
    {
        return [
            'value' => null,
            'availability' => $availability,
            'included_count' => null,
            'excluded_count' => null,
            'candidate_count' => null,
            'breakdown' => [],
            'source_record_ids' => [],
            'source_record_ids_truncated' => false,
            'filters_applied' => [],
        ];
    }

    /**
     * @param array<string, mixed>|null $contract
     * @param array<string, int> $breakdown
     * @param array<string, mixed> $evaluation
     * @return array<string, mixed>
     */
    private function result(
        string $key,
        string $label,
        string $domain,
        string $unit,
        ?int $value,
        string $availability,
        string $source,
        string $permission,
        string $description,
        CarbonImmutable $asOf,
        ?int $version,
        ?KpiDefinition $definition,
        ?array $contract = null,
        array $breakdown = [],
        ?array $evaluation = null,
    ): array {
        $contract ??= $this->catalogue->get($key) ?? [];
        $evaluation ??= $this->emptyEvaluation($availability);
        $dimensions = $definition instanceof KpiDefinition ? ($definition->dimensions ?? []) : ($contract['dimensions'] ?? []);
        $timezone = $this->catalogue->timezone();
        $date = $asOf->setTimezone($timezone)->toDateString();

        return [
            'key' => $key,
            'label' => $label,
            'domain' => $domain,
            'version' => $version,
            'definition_version' => $version,
            'catalogue_version' => $this->catalogue->version(),
            'unit' => $unit,
            'value' => $value,
            'state' => $availability === 'available' ? 'available' : 'unavailable',
            'availability' => $availability,
            'as_of' => $asOf->toIso8601String(),
            'period' => [
                'kind' => 'as_of',
                'start_date' => $date,
                'end_date' => $date,
                'timezone' => $timezone,
                'boundary' => 'inclusive_calendar_date',
                'historical' => false,
            ],
            'included_count' => $evaluation['included_count'],
            'excluded_count' => $evaluation['excluded_count'],
            'candidate_count' => $evaluation['candidate_count'],
            'freshness' => [
                'status' => 'evaluated_at_request',
                'evaluated_at' => CarbonImmutable::now($timezone)->toIso8601String(),
                'source_updated_at' => null,
                'staleness_seconds' => null,
            ],
            'provenance' => [
                'source' => $source,
                'source_model' => $contract['source_service'] ?? null,
                'calculation_service' => $contract['calculation_service'] ?? null,
                'calculation_type' => $contract['calculation_type'] ?? null,
                'formula' => $contract['formula'] ?? null,
                'definition_version' => $version,
                'filters' => $evaluation['filters_applied'],
                'source_record_ids' => $evaluation['source_record_ids'],
                'source_record_ids_limit' => self::MAX_PROVENANCE_RECORDS,
                'source_record_ids_truncated' => $evaluation['source_record_ids_truncated'],
            ],
            'source' => $source,
            'source_service' => $contract['source_service'] ?? null,
            'calculation_service' => $contract['calculation_service'] ?? null,
            'calculation_type' => $contract['calculation_type'] ?? null,
            'formula' => $contract['formula'] ?? null,
            'dimensions' => $dimensions,
            'dimension_breakdown' => $breakdown,
            'dimension_breakdown_limit' => self::MAX_DIMENSION_GROUPS,
            'supported_period' => $contract['supported_period'] ?? null,
            'missing_data' => $contract['missing_data'] ?? null,
            'approval_status' => $contract['approval_status'] ?? 'provisional',
            'unavailable_reason' => $contract['unavailable_reason'] ?? null,
            'permission' => $permission,
            'definition' => $description,
            'target_value' => $definition?->target_value,
            'warning_threshold' => $definition?->warning_threshold,
            'critical_threshold' => $definition?->critical_threshold,
            'threshold_status' => $this->thresholdStatus($definition, $value),
        ];
    }

    private function thresholdStatus(?KpiDefinition $definition, ?int $value): string
    {
        if ($value === null) {
            return 'unavailable';
        }
        if ($definition === null || $definition->threshold_direction === 'target_only') {
            return 'not_evaluated';
        }
        if ($definition->threshold_direction === 'higher_is_worse') {
            if ($definition->critical_threshold !== null && $value >= (float) $definition->critical_threshold) {
                return 'critical';
            }
            if ($definition->warning_threshold !== null && $value >= (float) $definition->warning_threshold) {
                return 'warning';
            }
        }
        if ($definition->threshold_direction === 'lower_is_worse') {
            if ($definition->critical_threshold !== null && $value <= (float) $definition->critical_threshold) {
                return 'critical';
            }
            if ($definition->warning_threshold !== null && $value <= (float) $definition->warning_threshold) {
                return 'warning';
            }
        }

        return 'normal';
    }
}
