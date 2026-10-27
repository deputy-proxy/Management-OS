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
    /**
     * Evaluate the allow-listed catalogue. An active, effective-dated database
     * definition overrides its static v1 definition without changing the key.
     *
     * @param  array<string, bool>  $permissions
     * @return array{catalogue_version: string, as_of: string, timezone: string, results: list<array<string, mixed>>}
     */
    public function current(array $permissions = [], ?CarbonImmutable $asOf = null): array
    {
        $catalogue = config('kpi_catalogue');
        $timezone = (string) ($catalogue['timezone'] ?? 'Europe/Bucharest');
        $asOf = ($asOf ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $date = $asOf->toDateString();
        $activeDefinitions = KpiDefinition::query()
            ->where('status', KpiDefinition::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->get()
            ->keyBy('key');

        $results = [];
        foreach ($catalogue['definitions'] as $key => $definition) {
            $active = $activeDefinitions->get($key);
            $results[] = $active instanceof KpiDefinition
                ? $this->aggregate($active, $permissions, $asOf, $date)
                : $this->aggregateStatic($key, $definition, $permissions, $asOf, $date);
        }

        return [
            'catalogue_version' => (string) $catalogue['version'],
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
        $timezone = (string) config('kpi_catalogue.timezone', 'Europe/Bucharest');
        $asOf = ($asOf ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $date ??= $asOf->toDateString();
        if (! ($permissions[$definition->permission] ?? false)) {
            return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, null, 'unavailable_permission_denied', $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition);
        }

        $value = $this->calculate($definition->calculation_type, $permissions, $date);

        return $this->result($definition->key, $definition->label, $definition->domain, $definition->unit, $value, $value === null ? 'unavailable' : 'available', $definition->source, $definition->permission, $definition->description, $asOf, $definition->version, $definition);
    }

    /** @param array<string, mixed> $definition
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    private function aggregateStatic(string $key, array $definition, array $permissions, CarbonImmutable $asOf, string $date): array
    {
        if (! ($permissions[$definition['permission']] ?? false)) {
            $value = null;
            $availability = 'unavailable_permission_denied';
        } else {
            $value = $this->calculate($definition['calculation_type'], $permissions, $date);
            $availability = $value === null ? 'unavailable' : 'available';
        }

        return $this->result($key, $definition['label'], $definition['domain'], $definition['unit'], $value, $availability, $definition['source'], $definition['permission'], $definition['description'], $asOf, null, null);
    }

    /** @param array<string, bool> $permissions */
    private function calculate(string $type, array $permissions, string $date): ?int
    {
        return match ($type) {
            'customer_record_count' => Customer::query()->count(),
            'unconverted_lead_count' => CustomerLead::query()->whereNull('converted_customer_id')->count(),
            'unknown_lead_source_count' => CustomerLead::query()->where('source_category', 'unknown')->count(),
            'open_task_count' => $this->taskCount(false, $date, $permissions),
            'overdue_task_count' => $this->taskCount(true, $date, $permissions),
            'competitor_offer_snapshot_count' => CompetitorOfferObservation::query()->count(),
            default => null,
        };
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
    private function result(string $key, string $label, string $domain, string $unit, ?int $value, string $availability, string $source, string $permission, string $description, CarbonImmutable $asOf, ?int $version, ?KpiDefinition $definition): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'domain' => $domain,
            'version' => $version,
            'catalogue_version' => config('kpi_catalogue.version'),
            'unit' => $unit,
            'value' => $value,
            'state' => $availability === 'available' ? 'available' : 'unavailable',
            'availability' => $availability,
            'as_of' => $asOf->toIso8601String(),
            'source' => $source,
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
