<?php

namespace App\Services;

use App\Models\KpiDefinition;
use App\Models\KpiDefinitionAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpiDefinitionService
{
    private const DRAFT_FIELDS = [
        'effective_from',
        'filters',
        'dimensions',
        'aggregation',
        'period',
        'target_value',
        'warning_threshold',
        'critical_threshold',
        'threshold_direction',
    ];

    public function queryForCurrentUser(): Builder
    {
        $this->authorize('kpi_definition.index', 'KPI definition view permission is required.');

        return KpiDefinition::query();
    }

    public function createDraft(array $attributes): KpiDefinition
    {
        $this->authorize('kpi_definition.create', 'KPI definition creation permission is required.');
        $summary = $this->changeSummary($attributes['change_summary'] ?? null, 'Created KPI definition draft.');
        $unsupported = array_diff(array_keys($attributes), array_merge(['key', 'change_summary'], self::DRAFT_FIELDS));
        if ($unsupported !== []) {
            throw ValidationException::withMessages(['configuration' => 'Only registered KPI keys and draft configuration fields may be supplied.']);
        }

        return DB::transaction(function () use ($attributes, $summary): KpiDefinition {
            unset($attributes['change_summary']);
            $definition = KpiDefinition::query()->create($attributes);
            $this->recordAudit($definition, 'created', $summary);

            return $definition;
        });
    }

    public function updateDraft(KpiDefinition $definition, array $attributes): KpiDefinition
    {
        $this->authorize('kpi_definition.update', 'KPI definition edit permission is required.');
        if ($definition->status !== KpiDefinition::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft KPI definitions can be edited.']);
        }

        $summary = $this->changeSummary($attributes['change_summary'] ?? null, 'Updated KPI definition draft.');
        unset($attributes['change_summary']);
        $unsupported = array_diff(array_keys($attributes), self::DRAFT_FIELDS);
        if ($unsupported !== []) {
            throw ValidationException::withMessages(['configuration' => 'Only draft configuration fields may be changed.']);
        }

        return DB::transaction(function () use ($definition, $attributes, $summary): KpiDefinition {
            $before = [];
            foreach (array_keys($attributes) as $field) {
                $before[$field] = $definition->getAttribute($field);
            }

            $definition->fill($attributes);
            $definition->save();

            $changes = [];
            foreach (array_keys($attributes) as $field) {
                $after = $definition->getAttribute($field);
                if ($before[$field] !== $after) {
                    $changes[$field] = ['before' => $before[$field], 'after' => $after];
                }
            }
            $this->recordAudit($definition, 'updated', $summary, $changes);

            return $definition->refresh();
        });
    }

    public function activate(KpiDefinition $definition): KpiDefinition
    {
        $this->authorize('kpi_definition.approve', 'KPI definition approval permission is required.');
        if ($definition->status !== KpiDefinition::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft KPI definitions can be activated.']);
        }

        return DB::transaction(function () use ($definition): KpiDefinition {
            $effectiveFrom = Carbon::parse($definition->effective_from)->startOfDay();
            if ($effectiveFrom->lt(Carbon::today(app(KpiCatalogueRegistry::class)->timezone()))) {
                throw ValidationException::withMessages(['effective_from' => 'KPI definitions cannot be activated retroactively. Create a prospective version or follow the documented correction process.']);
            }
            if ($effectiveFrom->lt(now()->startOfDay())) {
                throw ValidationException::withMessages(['effective_from' => 'KPI definitions cannot be activated retroactively.']);
            }
            $activeVersions = KpiDefinition::query()
                ->where('key', $definition->key)
                ->where('status', KpiDefinition::STATUS_ACTIVE)
                ->orderByDesc('effective_from')
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $effectiveFrom->toDateString()))
                ->lockForUpdate()
                ->get();

            foreach ($activeVersions as $active) {
                if (Carbon::parse($active->effective_from)->gte($effectiveFrom)) {
                    throw ValidationException::withMessages(['effective_from' => 'A KPI version already exists for this effective period.']);
                }
                $priorUntil = $active->effective_until !== null ? Carbon::parse($active->effective_until)->toDateString() : null;
                $active->effective_until = $effectiveFrom->copy()->subDay()->toDateString();
                $active->save();
                $this->recordAudit(
                    $active,
                    'superseded',
                    'Effective period closed when KPI definition version '.$definition->version.' was activated.',
                    ['effective_until' => ['before' => $priorUntil, 'after' => Carbon::parse($active->effective_until)->toDateString()]],
                );
            }

            $definition->status = KpiDefinition::STATUS_ACTIVE;
            $definition->approved_by = auth()->id();
            $definition->approved_at = now();
            $definition->save();
            $this->recordAudit($definition, 'activated', 'Approved and activated KPI definition version '.$definition->version.'.');

            return $definition->refresh();
        });
    }

    public function retire(KpiDefinition $definition): KpiDefinition
    {
        $this->authorize('kpi_definition.approve', 'KPI definition approval permission is required.');
        if ($definition->status !== KpiDefinition::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => 'Only active KPI definitions can be retired.']);
        }

        return DB::transaction(function () use ($definition): KpiDefinition {
            $definition->status = KpiDefinition::STATUS_RETIRED;
            $today = now()->toDateString();
            $currentUntil = Carbon::parse($definition->effective_until ?? $today)->toDateString();
            $retireUntil = min($currentUntil, $today);
            $effectiveFrom = Carbon::parse($definition->effective_from)->toDateString();
            $definition->effective_until = $retireUntil < $effectiveFrom ? $effectiveFrom : $retireUntil;
            $definition->retired_by = auth()->id();
            $definition->retired_at = now();
            $definition->save();
            $this->recordAudit($definition, 'retired', 'Retired KPI definition version '.$definition->version.'.');

            return $definition->refresh();
        });
    }

    private function authorize(string $ability, string $message): void
    {
        if (! hexa()->can($ability)) {
            throw new AuthorizationException($message);
        }
    }

    private function changeSummary(mixed $summary, string $fallback): string
    {
        if ($summary === null || trim((string) $summary) === '') {
            return $fallback;
        }

        $summary = trim((string) $summary);
        if (mb_strlen($summary) > 500) {
            throw ValidationException::withMessages(['change_summary' => 'Change summary must not exceed 500 characters.']);
        }

        return $summary;
    }

    /** @param array<string, mixed>|null $changes */
    private function recordAudit(KpiDefinition $definition, string $event, string $summary, ?array $changes = null): void
    {
        KpiDefinitionAudit::query()->create([
            'kpi_definition_id' => $definition->getKey(),
            'actor_id' => auth()->id(),
            'event' => $event,
            'effective_version' => $definition->version,
            'change_summary' => $summary,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }
}
