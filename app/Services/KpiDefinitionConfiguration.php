<?php

namespace App\Services;

use App\Models\CustomerLead;
use Illuminate\Validation\ValidationException;

class KpiDefinitionConfiguration
{
    public const MAX_FILTERS = 5;
    public const MAX_DIMENSIONS = 2;
    public const MAX_PREVIEW_ROWS = 100;

    /**
     * Fields are explicitly mapped to persisted columns. No user-supplied
     * property paths, SQL fragments, or operators reach the query builder.
     *
     * @return array<string, array{type: string, values: array<int, string>}>
     */
    private function fields(string $calculationType): array
    {
        return match ($calculationType) {
            'unconverted_lead_count', 'unknown_lead_source_count' => [
                'status' => ['type' => 'enum', 'values' => array_keys(CustomerLead::statuses())],
                'source_category' => ['type' => 'enum', 'values' => array_keys(CustomerLead::sourceCategories())],
            ],
            default => [],
        };
    }

    /** @return list<string> */
    public function dimensions(string $calculationType): array
    {
        return array_keys($this->fields($calculationType));
    }

    /**
     * @param array<int, mixed> $filters
     * @param array<int, mixed> $dimensions
     * @return array{filters: array<int, array{field: string, operator: string, value: string|array<int, string>}>, dimensions: list<string>, aggregation: string, period: array{kind: string, granularity: string, historical: bool}}
     */
    public function validate(string $calculationType, array $filters = [], array $dimensions = [], string $aggregation = 'count', ?array $period = null): array
    {
        if (! array_is_list($filters) || ! array_is_list($dimensions)) {
            throw ValidationException::withMessages(['configuration' => 'Filters and dimensions must be ordered lists.']);
        }
        $fields = $this->fields($calculationType);
        if (count($filters) > self::MAX_FILTERS) {
            throw ValidationException::withMessages(['filters' => 'A KPI can contain at most five filters.']);
        }
        if (count($dimensions) > self::MAX_DIMENSIONS) {
            throw ValidationException::withMessages(['dimensions' => 'A KPI can contain at most two dimensions.']);
        }
        if ($aggregation !== 'count') {
            throw ValidationException::withMessages(['aggregation' => 'Only the registered count aggregation is supported.']);
        }

        $validatedFilters = [];
        foreach ($filters as $index => $filter) {
            if (! is_array($filter)) {
                throw ValidationException::withMessages(["filters.{$index}" => 'Each filter must be a structured field/operator/value record.']);
            }
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? null;
            $value = $filter['value'] ?? null;
            if (! is_string($field) || ! isset($fields[$field])) {
                throw ValidationException::withMessages(["filters.{$index}.field" => 'Choose a field registered for this KPI calculation.']);
            }
            if (! in_array($operator, ['eq', 'neq', 'in'], true)) {
                throw ValidationException::withMessages(["filters.{$index}.operator" => 'Choose a supported filter operator.']);
            }
            $values = $operator === 'in' ? $value : [$value];
            if (! is_array($values) || $values === [] || count($values) > 20) {
                if ($operator !== 'in' || ! is_array($values) || $values === [] || count($values) > 20) {
                    throw ValidationException::withMessages(["filters.{$index}.value" => 'Provide between one and twenty allowed values.']);
                }
            }
            foreach ($values as $item) {
                if (! is_string($item) || ! in_array($item, $fields[$field]['values'], true)) {
                    throw ValidationException::withMessages(["filters.{$index}.value" => 'The filter value is not in the registered value set.']);
                }
            }
            $validatedFilters[] = ['field' => $field, 'operator' => $operator, 'value' => $operator === 'in' ? array_values($values) : $values[0]];
        }

        $validatedDimensions = [];
        foreach ($dimensions as $index => $dimension) {
            if (! is_string($dimension) || ! in_array($dimension, $this->dimensions($calculationType), true)) {
                throw ValidationException::withMessages(["dimensions.{$index}" => 'Choose a dimension registered for this KPI calculation.']);
            }
            if (in_array($dimension, $validatedDimensions, true)) {
                throw ValidationException::withMessages(["dimensions.{$index}" => 'Dimensions cannot be repeated.']);
            }
            $validatedDimensions[] = $dimension;
        }

        $period ??= ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false];
        if (($period['kind'] ?? null) !== 'as_of' || ($period['granularity'] ?? null) !== 'day' || ($period['historical'] ?? null) !== false) {
            throw ValidationException::withMessages(['period' => 'Only daily as-of reporting is currently supported.']);
        }

        return [
            'filters' => $validatedFilters,
            'dimensions' => $validatedDimensions,
            'aggregation' => $aggregation,
            'period' => ['kind' => 'as_of', 'granularity' => 'day', 'historical' => false],
        ];
    }

    /**
     * Preview only uses caller-supplied synthetic rows and is deliberately
     * bounded. It never queries production records.
     *
     * @param array<int, mixed> $rows
     * @param array<string, mixed> $configuration
     * @return array{synthetic: bool, sample_size: int, value: int, breakdown: array<string, int>}
     */
    public function preview(string $calculationType, array $configuration, array $rows): array
    {
        if (! array_is_list($rows)) {
            throw ValidationException::withMessages(['preview' => 'Synthetic preview rows must be an ordered list.']);
        }
        if (count($rows) > self::MAX_PREVIEW_ROWS) {
            throw ValidationException::withMessages(['preview' => 'Synthetic previews are limited to 100 rows.']);
        }
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["preview.{$index}" => 'Each synthetic sample row must be an object-like array.']);
            }
        }
        $filters = $configuration['filters'] ?? [];
        $dimensions = $configuration['dimensions'] ?? [];
        $aggregation = $configuration['aggregation'] ?? 'count';
        $period = $configuration['period'] ?? null;
        if (! is_array($filters) || ! is_array($dimensions) || ! is_string($aggregation) || ($period !== null && ! is_array($period))) {
            throw ValidationException::withMessages(['configuration' => 'Synthetic preview filters and dimensions must be lists.']);
        }
        $config = $this->validate(
            $calculationType,
            $filters,
            $dimensions,
            $aggregation,
            $period,
        );

        $matched = array_values(array_filter($rows, function (array $row) use ($config): bool {
            foreach ($config['filters'] as $filter) {
                $actual = $row[$filter['field']] ?? null;
                $expected = $filter['value'];
                $matches = match ($filter['operator']) {
                    'eq' => $actual === $expected,
                    'neq' => $actual !== $expected,
                    'in' => in_array($actual, $expected, true),
                };
                if (! $matches) {
                    return false;
                }
            }

            return true;
        }));

        $breakdown = [];
        if ($config['dimensions'] !== []) {
            foreach ($matched as $row) {
                $key = collect($config['dimensions'])
                    ->map(fn (string $field): string => $field.'='.((string) ($row[$field] ?? '(empty)')))
                    ->implode(' / ');
                $breakdown[$key] = ($breakdown[$key] ?? 0) + 1;
            }
            ksort($breakdown);
        }

        return ['synthetic' => true, 'sample_size' => count($rows), 'value' => count($matched), 'breakdown' => $breakdown];
    }
}
