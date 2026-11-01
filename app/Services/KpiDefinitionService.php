<?php

namespace App\Services;

use App\Models\KpiDefinition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpiDefinitionService
{
    public function activate(KpiDefinition $definition): KpiDefinition
    {
        if (! hexa()->can('kpi_definition.approve')) {
            throw new AuthorizationException('KPI definition approval permission is required.');
        }
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
                $active->effective_until = $effectiveFrom->copy()->subDay()->toDateString();
                $active->save();
            }

            $definition->status = KpiDefinition::STATUS_ACTIVE;
            $definition->approved_by = auth()->id();
            $definition->approved_at = now();
            $definition->save();

            return $definition->refresh();
        });
    }

    public function retire(KpiDefinition $definition): KpiDefinition
    {
        if (! hexa()->can('kpi_definition.approve')) {
            throw new AuthorizationException('KPI definition approval permission is required.');
        }
        if ($definition->status !== KpiDefinition::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => 'Only active KPI definitions can be retired.']);
        }

        $definition->status = KpiDefinition::STATUS_RETIRED;
        $today = now()->toDateString();
        $currentUntil = Carbon::parse($definition->effective_until ?? $today)->toDateString();
        $retireUntil = min($currentUntil, $today);
        $effectiveFrom = Carbon::parse($definition->effective_from)->toDateString();
        $definition->effective_until = $retireUntil < $effectiveFrom ? $effectiveFrom : $retireUntil;
        $definition->retired_by = auth()->id();
        $definition->retired_at = now();
        $definition->save();

        return $definition->refresh();
    }
}
