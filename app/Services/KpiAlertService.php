<?php

namespace App\Services;

use App\Contracts\KpiAlertDeliveryAdapter;
use App\Models\KpiAlert;
use App\Models\KpiAlertEvent;
use App\Models\KpiDefinition;
use App\Models\User;
use Carbon\CarbonImmutable;
use Hexters\HexaLite\Helpers\HexaHelper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpiAlertService
{
    public function __construct(
        private readonly KpiAggregationService $aggregation,
        private readonly KpiAlertDeliveryAdapter $delivery,
    ) {}

    public function queryForCurrentUser(): Builder
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new AuthorizationException('Authentication is required to view KPI alerts.');
        }

        $helper = (new HexaHelper)->user($user);
        if ($helper->can(['kpi_alert.index', 'kpi_alert.manage'])) {
            return KpiAlert::query()->with(['definition', 'owner']);
        }
        if ($helper->can(['kpi_alert.view_own', 'kpi_alert.manage_own'])) {
            return KpiAlert::query()->where('owner_id', $user->getKey())->with(['definition', 'owner']);
        }

        throw new AuthorizationException('KPI alert view permission is required.');
    }

    /**
     * Evaluate a single permission-scoped KPI result. Unknown or stale source
     * freshness never creates or resolves an incident.
     *
     * @param  array<string, mixed>  $result
     */
    public function evaluate(KpiDefinition $definition, array $result, ?CarbonImmutable $now = null): ?KpiAlert
    {
        if ($definition->status !== KpiDefinition::STATUS_ACTIVE || ! $definition->alerts_enabled || ! $definition->alert_owner_id) {
            return null;
        }

        $now = ($now ?? CarbonImmutable::now())->setTimezone((string) config('app.timezone', 'UTC'));
        $freshness = $result['freshness'] ?? null;
        if (($result['availability'] ?? null) !== 'available'
            || ! is_numeric($result['value'] ?? null)
            || ! is_array($freshness)
            || ($freshness['status'] ?? null) !== 'fresh'
            || ! is_numeric($freshness['staleness_seconds'] ?? null)
            || (int) $freshness['staleness_seconds'] > (int) config('kpi_alerts.maximum_freshness_seconds', 86400)) {
            return null;
        }

        $severity = $result['threshold_status'] ?? null;
        if (! in_array($severity, ['warning', 'critical'], true)) {
            if ($severity === 'normal') {
                $this->resolveForNormalResult($definition, $now);
            }

            return null;
        }

        $threshold = $severity === 'critical' ? $definition->critical_threshold : $definition->warning_threshold;
        if ($threshold === null) {
            return null;
        }

        $owner = $definition->alertOwner()->first();
        if (! $owner instanceof User) {
            return null;
        }

        $dedupeKey = $definition->key.':'.$definition->version;
        $evidence = [
            'kpi_key' => $definition->key,
            'definition_version' => $definition->version,
            'observed_value' => (float) $result['value'],
            'threshold' => (float) $threshold,
            'warning_threshold' => $definition->warning_threshold === null ? null : (float) $definition->warning_threshold,
            'critical_threshold' => $definition->critical_threshold === null ? null : (float) $definition->critical_threshold,
            'threshold_direction' => $definition->threshold_direction,
            'as_of' => $result['as_of'] ?? null,
            'source' => $result['source'] ?? null,
            'source_model' => data_get($result, 'provenance.source_model'),
            'freshness' => $freshness,
            'source_record_ids' => array_slice((array) data_get($result, 'provenance.source_record_ids', []), 0, KpiAggregationService::MAX_PROVENANCE_RECORDS),
        ];

        [$alert, $created] = DB::transaction(function () use ($definition, $dedupeKey, $severity, $threshold, $evidence, $owner, $now): array {
            KpiDefinition::query()->whereKey($definition->getKey())->lockForUpdate()->firstOrFail();
            $active = KpiAlert::query()
                ->where('dedupe_key', $dedupeKey)
                ->whereIn('status', [KpiAlert::STATUS_OPEN, KpiAlert::STATUS_ACKNOWLEDGED])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($active instanceof KpiAlert) {
                $previousSeverity = $active->severity;
                $active->fill([
                    'severity' => $severity,
                    'observed_value' => $evidence['observed_value'],
                    'trigger_threshold' => $threshold,
                    'evidence' => $evidence,
                    'last_seen_at' => $now,
                ])->save();
                if ($previousSeverity !== $severity) {
                    $this->recordEvent($active, 'severity_changed', $active->status, $active->status, ['from' => $previousSeverity, 'to' => $severity], null);
                }

                return [$active->refresh(), false];
            }

            $latest = KpiAlert::query()->where('dedupe_key', $dedupeKey)->latest('id')->first();
            if ($latest?->cooldown_until !== null && CarbonImmutable::parse($latest->cooldown_until)->isAfter($now)) {
                return [null, false];
            }

            $alert = KpiAlert::query()->create([
                'kpi_definition_id' => $definition->getKey(),
                'owner_id' => $owner->getKey(),
                'dedupe_key' => $dedupeKey,
                'severity' => $severity,
                'status' => KpiAlert::STATUS_OPEN,
                'observed_value' => $evidence['observed_value'],
                'trigger_threshold' => $threshold,
                'evidence' => $evidence,
                'evidence_url' => null,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
            $alert->update(['evidence_url' => route('filament.admin.general.resources.kpi-alerts.view', ['record' => $alert->getKey()])]);
            $this->recordEvent($alert, 'raised', null, KpiAlert::STATUS_OPEN, $evidence, null);

            return [$alert, true];
        });

        if ($alert instanceof KpiAlert && $created && config('kpi_alerts.delivery_enabled', false)
            && (new HexaHelper)->user($owner)->can('kpi_alert.notify')) {
            $this->delivery->deliver($owner, $alert->load('definition'), 'raised');
        }

        return $alert;
    }

    /** Evaluate active alert-enabled definitions as their configured owners. */
    public function evaluateConfiguredAlerts(): int
    {
        $evaluated = 0;
        $now = CarbonImmutable::now((string) config('app.timezone', 'UTC'));
        $date = $now->toDateString();
        $this->resolveAlertsForInactiveRules($now);
        $definitions = KpiDefinition::query()
            ->with('alertOwner')
            ->where('status', KpiDefinition::STATUS_ACTIVE)
            ->where('alerts_enabled', true)
            ->whereNotNull('alert_owner_id')
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->get();
        $guard = Auth::guard();
        $previousUser = $guard->user();

        try {
            foreach ($definitions as $definition) {
                $owner = $definition->alertOwner;
                if (! $owner instanceof User) {
                    continue;
                }

                $guard->setUser($owner);
                $permissions = (new HexaHelper)->user($owner);
                $result = $this->aggregation->aggregate($definition, [
                    $definition->permission => $permissions->can($definition->permission),
                    'task.view_all' => $permissions->can('task.view_all'),
                ]);
                $this->evaluate($definition, $result);
                $evaluated++;
            }
        } finally {
            if ($previousUser instanceof Authenticatable) {
                $guard->setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }

        return $evaluated;
    }

    public function acknowledge(KpiAlert $alert): KpiAlert
    {
        $this->authorizeManagement($alert);
        if ($alert->status === KpiAlert::STATUS_ACKNOWLEDGED) {
            return $alert;
        }
        if ($alert->status !== KpiAlert::STATUS_OPEN) {
            throw ValidationException::withMessages(['status' => 'Only open KPI alerts can be acknowledged.']);
        }

        return DB::transaction(function () use ($alert): KpiAlert {
            $locked = KpiAlert::query()->whereKey($alert->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== KpiAlert::STATUS_OPEN) {
                throw ValidationException::withMessages(['status' => 'This KPI alert is no longer open.']);
            }
            $locked->update([
                'status' => KpiAlert::STATUS_ACKNOWLEDGED,
                'acknowledged_at' => now(),
                'acknowledged_by' => Auth::id(),
            ]);
            $this->recordEvent($locked, 'acknowledged', KpiAlert::STATUS_OPEN, KpiAlert::STATUS_ACKNOWLEDGED, [], Auth::id());

            return $locked->refresh();
        });
    }

    public function resolve(KpiAlert $alert): KpiAlert
    {
        $this->authorizeManagement($alert);

        return $this->resolveIncident($alert, 'manually_resolved', Auth::id(), ['reason' => 'manual_management_action']);
    }

    public function canManage(KpiAlert $alert): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }
        $helper = (new HexaHelper)->user($user);

        return $helper->can('kpi_alert.manage')
            || ((int) $alert->owner_id === (int) $user->getKey() && $helper->can('kpi_alert.manage_own'));
    }

    private function authorizeManagement(KpiAlert $alert): void
    {
        if (! $this->canManage($alert)) {
            throw new AuthorizationException('KPI alert management permission is required.');
        }
    }

    private function resolveAlertsForInactiveRules(CarbonImmutable $now): void
    {
        $date = $now->toDateString();
        KpiAlert::query()
            ->whereIn('status', [KpiAlert::STATUS_OPEN, KpiAlert::STATUS_ACKNOWLEDGED])
            ->with('definition')
            ->get()
            ->each(function (KpiAlert $alert) use ($date, $now): void {
                $definition = $alert->definition;
                $isEffective = $definition instanceof KpiDefinition
                    && $definition->status === KpiDefinition::STATUS_ACTIVE
                    && $definition->alerts_enabled
                    && $definition->alert_owner_id !== null
                    && CarbonImmutable::parse($definition->effective_from)->toDateString() <= $date
                    && ($definition->effective_until === null || CarbonImmutable::parse($definition->effective_until)->toDateString() >= $date);

                if (! $isEffective) {
                    $this->resolveIncident($alert, 'rule_deactivated', null, ['reason' => 'definition_not_active_or_effective'], $now);
                }
            });
    }

    private function resolveForNormalResult(KpiDefinition $definition, CarbonImmutable $now): void
    {
        $dedupeKey = $definition->key.':'.$definition->version;
        $alert = KpiAlert::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereIn('status', [KpiAlert::STATUS_OPEN, KpiAlert::STATUS_ACKNOWLEDGED])
            ->latest('id')
            ->first();
        if ($alert instanceof KpiAlert) {
            $this->resolveIncident($alert, 'resolved', null, ['reason' => 'threshold_returned_to_normal'], $now);
        }
    }

    private function resolveIncident(KpiAlert $alert, string $event, ?int $actorId, array $metadata, ?CarbonImmutable $now = null): KpiAlert
    {
        if ($alert->status === KpiAlert::STATUS_RESOLVED) {
            return $alert;
        }

        return DB::transaction(function () use ($alert, $event, $actorId, $metadata, $now): KpiAlert {
            $locked = KpiAlert::query()->whereKey($alert->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === KpiAlert::STATUS_RESOLVED) {
                return $locked;
            }
            $from = $locked->status;
            $resolvedAt = $now ?? CarbonImmutable::now();
            $cooldown = max(1, (int) ($locked->definition->alert_cooldown_minutes ?? config('kpi_alerts.default_cooldown_minutes', 60)));
            $locked->update([
                'status' => KpiAlert::STATUS_RESOLVED,
                'resolved_at' => $resolvedAt,
                'resolved_by' => $actorId,
                'cooldown_until' => $resolvedAt->addMinutes($cooldown),
            ]);
            $this->recordEvent($locked, $event, $from, KpiAlert::STATUS_RESOLVED, $metadata, $actorId);

            return $locked->refresh();
        });
    }

    private function recordEvent(KpiAlert $alert, string $event, ?string $from, ?string $to, array $metadata, ?int $actorId): void
    {
        KpiAlertEvent::query()->create([
            'kpi_alert_id' => $alert->getKey(),
            'actor_id' => $actorId,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
