<?php

namespace App\Services;

use App\Models\CompetitorOfferObservation;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\KpiDefinition;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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

    public function __construct(private readonly KpiCatalogueRegistry $catalogue) {}

    /**
     * Evaluate the allow-listed catalogue. An active, effective-dated database
     * definition overrides its static v1 definition without changing the key.
     *
     * @param  array<string, bool>  $permissions
     * @return array{catalogue_version: string, catalogue_approval_status: string, as_of: string, timezone: string, results: list<array<string, mixed>>}
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
        if (! ($permissions[$definition->permission] ?? false)) {
            return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, null, 'unavailable_permission_denied', $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition);
        }

        $contract = $this->catalogue->get($definition->key);
        if ($contract === null || $contract['availability'] !== 'available') {
            $reason = $contract['availability'] ?? 'unavailable_missing_calculation';

            return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, null, $reason, $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition, $contract);
        }

        $filters = $definition->getAttribute('filters') ?? [];
        $dimensions = $definition->getAttribute('dimensions') ?? [];
        if (! is_array($filters) || ! is_array($dimensions)) {
            return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, null, 'unavailable_invalid_definition', $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition, $contract);
        }
        $value = $this->calculate($definition->calculation_type, $permissions, $date, $filters);
        $breakdown = $this->dimensionBreakdown($definition->calculation_type, $filters, $dimensions);

        return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, $value, $value === null ? 'unavailable' : 'available', $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition, $contract, $breakdown);
    }

    /** @param array<string, mixed> $definition
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    private function aggregateStatic(string $key, array $definition, array $permissions, CarbonImmutable $asOf, string $date): array
    {
        if ($definition['availability'] !== 'available') {
            $value = null;
            $availability = $definition['availability'];
        } elseif (! ($permissions[$definition['permission']] ?? false)) {
            $value = null;
            $availability = 'unavailable_permission_denied';
        } else {
            $value = $this->calculate($definition['calculation_type'], $permissions, $date);
            $availability = $value === null ? 'unavailable' : 'available';
        }

        return $this->result($key, $definition['label'], $definition['domain'], $definition['unit'], $value, $availability, $definition['source'], $definition['permission'], $definition['description'], $asOf, null, null, $definition);
    }

    /** @param array<string, bool> $permissions */
    private function calculate(string $type, array $permissions, string $date, array $filters = []): ?int
    {
        return match ($type) {
            'customer_record_count' => Customer::query()->count(),
            'unconverted_lead_count' => $this->applyLeadFilters(CustomerLead::query()->whereNull('converted_customer_id'), $filters)->count(),
            'unknown_lead_source_count' => $this->applyLeadFilters(CustomerLead::query()->where('source_category', 'unknown'), $filters)->count(),
            'open_task_count' => $this->taskCount(false, $date, $permissions),
            'overdue_task_count' => $this->taskCount(true, $date, $permissions),
            'competitor_offer_snapshot_count' => CompetitorOfferObservation::query()->count(),
            default => null,
        };
    }

    /**
     * Apply only the validated lead filter contract. Field names and operators
     * are checked again at execution time so stale or tampered stored JSON
     * cannot widen the query surface.
     *
     * @param array<int, array{field: string, operator: string, value: string|array<int, string>}> $filters
     */
    private function applyLeadFilters(Builder $query, array $filters): Builder
    {
        $configuration = app(KpiDefinitionConfiguration::class);
        $validated = $configuration->validate('unconverted_lead_count', $filters);
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
     * @param array<int, array{field: string, operator: string, value: string|array<int, string>}> $filters
     * @param array<int, string> $dimensions
     * @return array<string, int>
     */
    private function dimensionBreakdown(string $type, array $filters, array $dimensions): array
    {
        $allowed = app(KpiDefinitionConfiguration::class)->dimensions($type);
        if ($dimensions === [] || ! in_array($type, ['unconverted_lead_count', 'unknown_lead_source_count'], true)) {
            return [];
        }
        foreach ($dimensions as $dimension) {
            if (! in_array($dimension, $allowed, true)) {
                return [];
            }
        }

        $query = CustomerLead::query();
        if ($type === 'unconverted_lead_count') {
            $query->whereNull('converted_customer_id');
        } else {
            $query->where('source_category', 'unknown');
        }
        $rows = $this->applyLeadFilters($query, $filters)
            ->select($dimensions)
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy(...$dimensions)
            ->limit(50)
            ->get();

        $breakdown = [];
        foreach ($rows as $row) {
            $label = collect($dimensions)
                ->map(fn (string $field): string => $field.'='.((string) ($row->getAttribute($field) ?? '(empty)')))
                ->implode(' / ');
            $breakdown[$label] = (int) $row->aggregate;
        }

        return $breakdown;
    }

    /** @param array<string, bool> $permissions */
    private function taskCount(bool $overdueOnly, string $date, array $permissions): ?int
    {
        $query = Task::query()->where('status', '!=', 'done');
        if ($overdueOnly) {
            $query->whereDate('due_date', '<', $date);
        }
        if ($permissions['task.view_all'] ?? false) {
            return $query->count();
        }
        $userId = auth()->id();
        if ($userId === null) {
            return null;
        }

        return $query->where(fn (Builder $scope) => $scope->where('assigned_to', $userId)->orWhere('created_by', $userId))->count();
    }

    /** @return array<string, mixed> */
    private function result(string $key, string $label, string $domain, string $unit, ?int $value, string $availability, string $source, string $permission, string $description, CarbonImmutable $asOf, ?int $version, ?KpiDefinition $definition, ?array $contract = null, array $breakdown = []): array
    {
        $contract ??= $this->catalogue->get($key) ?? [];
        $dimensions = $definition instanceof KpiDefinition ? ($definition->dimensions ?? []) : ($contract['dimensions'] ?? []);

        return [
            'key' => $key,
            'label' => $label,
            'domain' => $domain,
            'version' => $version,
            'catalogue_version' => $this->catalogue->version(),
            'unit' => $unit,
            'value' => $value,
            'state' => $availability === 'available' ? 'available' : 'unavailable',
            'availability' => $availability,
            'as_of' => $asOf->toIso8601String(),
            'source' => $source,
            'source_service' => $contract['source_service'] ?? null,
            'calculation_service' => $contract['calculation_service'] ?? null,
            'calculation_type' => $contract['calculation_type'] ?? null,
            'formula' => $contract['formula'] ?? null,
            'dimensions' => $dimensions,
            'dimension_breakdown' => $breakdown,
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
