<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class KpiCatalogueRegistry
{
    /** @var array<string, mixed> */
    private array $catalogue;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $indexedDefinitions = null;

    /** @param array<string, mixed>|null $catalogue */
    public function __construct(?array $catalogue = null)
    {
        $this->catalogue = $catalogue ?? config('kpi_catalogue', []);
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if ($this->indexedDefinitions !== null) {
            return $this->indexedDefinitions;
        }

        $definitions = $this->catalogue['definitions'] ?? null;
        if (! is_array($definitions) || ! array_is_list($definitions)) {
            throw new InvalidArgumentException('KPI catalogue definitions must be a list of keyed definition records.');
        }

        $allowedDimensions = array_keys($this->catalogue['dimensions'] ?? []);
        $indexed = [];
        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                throw new InvalidArgumentException('Each KPI catalogue definition must be an array.');
            }
            $key = $definition['key'] ?? null;
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]+$/', $key)) {
                throw new InvalidArgumentException('Each KPI catalogue definition must have a valid stable key.');
            }
            if (array_key_exists($key, $indexed)) {
                throw new InvalidArgumentException("Duplicate KPI catalogue key [{$key}].");
            }
            foreach (['label', 'description', 'domain', 'unit', 'source', 'source_service', 'calculation_service', 'calculation_type', 'permission', 'missing_data', 'formula', 'date_basis'] as $field) {
                if (! is_string($definition[$field] ?? null) || trim($definition[$field]) === '') {
                    throw new InvalidArgumentException("KPI [{$key}] is missing required contract field [{$field}].");
                }
            }
            $dimensions = $definition['dimensions'] ?? null;
            if (! is_array($dimensions) || ! array_is_list($dimensions)) {
                throw new InvalidArgumentException("KPI [{$key}] dimensions must be a list.");
            }
            foreach ($dimensions as $dimension) {
                if (! is_string($dimension) || ! in_array($dimension, $allowedDimensions, true)) {
                    throw new InvalidArgumentException("KPI [{$key}] declares an unsupported dimension.");
                }
            }
            if (count($dimensions) !== count(array_unique($dimensions))) {
                throw new InvalidArgumentException("KPI [{$key}] contains duplicate dimensions.");
            }
            $period = $definition['supported_period'] ?? null;
            if (! is_array($period) || ($period['kind'] ?? null) !== 'as_of' || ($period['granularity'] ?? null) !== 'day' || ($period['historical'] ?? null) !== false) {
                throw new InvalidArgumentException("KPI [{$key}] must declare the supported daily as-of period contract.");
            }
            if (! class_exists($definition['source_service']) || ! is_subclass_of($definition['source_service'], Model::class)) {
                $definition['availability'] = 'unavailable_missing_source';
                $definition['unavailable_reason'] = 'The configured source model is not available in this application build.';
            } elseif (! class_exists($definition['calculation_service'])
                || ! is_a($definition['calculation_service'], KpiAggregationService::class, true)
                || ! in_array($definition['calculation_type'], KpiAggregationService::CALCULATION_TYPES, true)) {
                $definition['availability'] = 'unavailable_missing_calculation';
                $definition['unavailable_reason'] = 'The configured calculation service or calculation type is not registered.';
            } else {
                $definition['availability'] = 'available';
                $definition['unavailable_reason'] = null;
            }
            $definition['approval_status'] = $definition['approval_status'] ?? 'provisional';
            if (! in_array($definition['approval_status'], ['provisional', 'approved'], true)) {
                throw new InvalidArgumentException("KPI [{$key}] has an unsupported approval status.");
            }
            $indexed[$key] = $definition;
        }

        return $this->indexedDefinitions = $indexed;
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        $options = [];
        foreach ($this->all() as $key => $definition) {
            if ($definition['availability'] === 'available') {
                $options[$key] = $definition['label'];
            }
        }

        return $options;
    }

    public function version(): string
    {
        $version = $this->catalogue['version'] ?? null;
        if (! is_string($version) || ! preg_match('/^v[1-9][0-9]*$/', $version)) {
            throw new InvalidArgumentException('KPI catalogue version must use the vN format.');
        }

        return $version;
    }

    public function timezone(): string
    {
        $timezone = $this->catalogue['timezone'] ?? 'Europe/Bucharest';
        if (! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('KPI catalogue timezone must be a valid IANA timezone.');
        }

        return $timezone;
    }

    public function approvalStatus(): string
    {
        $status = $this->catalogue['approval_status'] ?? 'provisional';
        if (! is_string($status) || ! in_array($status, ['provisional', 'approved'], true)) {
            throw new InvalidArgumentException('KPI catalogue approval status must be provisional or approved.');
        }

        return $status;
    }
}
