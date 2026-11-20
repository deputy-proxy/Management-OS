<?php

namespace App\Mcp\Tools\Executive;

use App\Mcp\Authorization\AuthorizedMcpCapabilityRegistrar;
use App\Models\KpiAlert;
use App\Models\User;
use App\Services\AdminOverviewDashboardData;
use App\Services\KpiAggregationService;
use App\Services\KpiAlertService;
use App\Services\KpiCatalogueRegistry;
use App\Services\TasksAndProjectsData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Mcp\Server\Builder;

final class ExecutiveMcpCapabilityRegistrar extends AuthorizedMcpCapabilityRegistrar
{
    private const DATA_PERMISSIONS = [
        'student.index',
        'employee.index',
        'customer.index',
        'customer_lead.index',
        'competitor_offer.index',
        'project.index',
        'project_phase.index',
        'task.index',
        'task.view_all',
        'article.index',
        'newsletter.index',
        'paid_campaign.index',
    ];

    public function register(Builder $builder): void
    {
        $this->addAuthorizedTool(
            $builder,
            'get_executive_overview',
            fn (User $principal): array => $this->executiveOverview($principal),
            'Returns permission-filtered executive overview metrics, provisional KPI signals, and limited recent operational context.',
            $this->emptyInputSchema(),
        );

        $this->addAuthorizedTool(
            $builder,
            'get_management_kpis',
            fn (User $principal): array => $this->managementKpis($principal),
            'Returns the code-backed KPI catalogue evaluated with the current principal permissions and reporting timezone.',
            $this->emptyInputSchema(),
        );

        $this->addAuthorizedTool(
            $builder,
            'get_kpi_alerts',
            fn (User $principal, array $arguments): array => $this->kpiAlerts($principal, $arguments),
            'Returns KPI alerts visible to the authenticated AI principal. Results are owner-scoped and bounded.',
            [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['open', 'acknowledged', 'resolved']],
                    'severity' => ['type' => 'string', 'enum' => ['warning', 'critical']],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ],
                'additionalProperties' => false,
            ],
        );

        $this->addAuthorizedTool(
            $builder,
            'get_business_health',
            fn (User $principal): array => $this->businessHealth($principal),
            'Returns available operational KPI signals without inventing an overall business-health score or treating provisional metrics as approved.',
            $this->emptyInputSchema(),
        );

        $this->addAuthorizedTool(
            $builder,
            'get_management_priorities',
            fn (User $principal, array $arguments): array => $this->managementPriorities($principal, $arguments),
            'Returns visible overdue and upcoming tasks ordered by due date. It does not invent a priority score.',
            [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                ],
                'additionalProperties' => false,
            ],
        );

        $this->addAuthorizedTool(
            $builder,
            'get_period_comparison',
            fn (User $principal, array $arguments): array => $this->periodComparison($arguments),
            'Validates two reporting periods and reports whether the current KPI catalogue can compare them. Historical KPI reconstruction is not currently supported.',
            [
                'type' => 'object',
                'properties' => [
                    'current_period' => [
                        'type' => 'object',
                        'properties' => [
                            'start_date' => ['type' => 'string', 'format' => 'date'],
                            'end_date' => ['type' => 'string', 'format' => 'date'],
                        ],
                        'required' => ['start_date', 'end_date'],
                        'additionalProperties' => false,
                    ],
                    'comparison_period' => [
                        'type' => 'object',
                        'properties' => [
                            'start_date' => ['type' => 'string', 'format' => 'date'],
                            'end_date' => ['type' => 'string', 'format' => 'date'],
                        ],
                        'required' => ['start_date', 'end_date'],
                        'additionalProperties' => false,
                    ],
                    'timezone' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                ],
                'required' => ['current_period', 'comparison_period'],
                'additionalProperties' => false,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function executiveOverview(User $principal): array
    {
        return $this->withPrincipalContext($principal, function () use ($principal): array {
            $now = $this->reportingNow();
            $permissions = $this->dataPermissions($principal);
            $summary = app(AdminOverviewDashboardData::class)->summary($permissions, $now);
            $canViewProjects = $permissions['project.index'] ?? false;

            return [
                'status' => 'available',
                'as_of' => $now->toIso8601String(),
                'timezone' => app(KpiCatalogueRegistry::class)->timezone(),
                'metrics' => array_map(static fn (array $metric): array => [
                    'label' => $metric['label'],
                    'value' => $metric['value'],
                    'definition' => $metric['definition'],
                ], $summary['metrics']),
                'kpis' => $this->safeKpiCatalogue($summary['kpis']),
                'overdue_tasks' => $summary['overdue_tasks']->map(static fn ($task): array => [
                    'action' => (string) $task->action,
                    'due_date' => $task->due_date?->toDateString(),
                    'project' => $canViewProjects ? $task->project?->title : null,
                ])->values()->all(),
                'recent_activity' => $summary['recent_activity']->map(static fn ($activity): array => [
                    'event' => $activity->event,
                    'subject_type' => class_basename((string) $activity->subject_type),
                    'created_at' => $activity->created_at?->toIso8601String(),
                ])->values()->all(),
                'activity_available' => (bool) $summary['activity_available'],
            ];
        });
    }

    /** @return array<string, mixed> */
    private function managementKpis(User $principal): array
    {
        return $this->withPrincipalContext($principal, function () use ($principal): array {
            $now = $this->reportingNow();

            return $this->safeKpiCatalogue(
                app(KpiAggregationService::class)->current($this->dataPermissions($principal), $now),
            );
        });
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function kpiAlerts(User $principal, array $arguments): array
    {
        /** @var array{status?: string|null, severity?: string|null, limit?: int} $validated */
        $validated = Validator::make($arguments, [
            'status' => ['sometimes', 'nullable', Rule::in([KpiAlert::STATUS_OPEN, KpiAlert::STATUS_ACKNOWLEDGED, KpiAlert::STATUS_RESOLVED])],
            'severity' => ['sometimes', 'nullable', Rule::in(['warning', 'critical'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ])->validate();

        return $this->withPrincipalContext($principal, function () use ($principal, $validated): array {
            $query = app(KpiAlertService::class)->queryForCurrentUser()
                ->where('owner_id', $principal->getKey());
            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }
            if (isset($validated['severity'])) {
                $query->where('severity', $validated['severity']);
            }

            $limit = (int) ($validated['limit'] ?? 20);
            $total = (clone $query)->count();
            /** @var Collection<int, KpiAlert> $alerts */
            $alerts = $query->with('definition')
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            return [
                'status' => 'available',
                'returned_count' => $alerts->count(),
                'total_visible_count' => $total,
                'has_more' => $total > $alerts->count(),
                'alerts' => $alerts->map(static fn (KpiAlert $alert): array => [
                    'severity' => $alert->severity,
                    'status' => $alert->status,
                    'observed_value' => (float) $alert->observed_value,
                    'trigger_threshold' => (float) $alert->trigger_threshold,
                    'first_seen_at' => CarbonImmutable::parse($alert->first_seen_at)->toIso8601String(),
                    'last_seen_at' => CarbonImmutable::parse($alert->last_seen_at)->toIso8601String(),
                    'acknowledged_at' => filled($alert->acknowledged_at) ? CarbonImmutable::parse($alert->acknowledged_at)->toIso8601String() : null,
                    'resolved_at' => filled($alert->resolved_at) ? CarbonImmutable::parse($alert->resolved_at)->toIso8601String() : null,
                    'kpi_key' => $alert->definition?->key,
                    'kpi_label' => $alert->definition?->label,
                    'as_of' => data_get($alert->evidence, 'as_of'),
                    'source' => data_get($alert->evidence, 'source'),
                ])->values()->all(),
            ];
        });
    }

    /** @return array<string, mixed> */
    private function businessHealth(User $principal): array
    {
        return $this->withPrincipalContext($principal, function () use ($principal): array {
            $catalogue = app(KpiAggregationService::class)->current($this->dataPermissions($principal), $this->reportingNow());

            return [
                'assessment_status' => 'not_scored',
                'reason' => 'No approved business-health formula exists. The available catalogue is provisional and must not be combined into an invented score.',
                'catalogue_version' => $catalogue['catalogue_version'],
                'catalogue_approval_status' => $catalogue['catalogue_approval_status'],
                'as_of' => $catalogue['as_of'],
                'timezone' => $catalogue['timezone'],
                'signals' => collect($catalogue['results'])->map(static fn (array $result): array => [
                    'key' => $result['key'],
                    'label' => $result['label'],
                    'domain' => $result['domain'],
                    'unit' => $result['unit'],
                    'value' => $result['value'],
                    'state' => $result['state'],
                    'availability' => $result['availability'],
                    'threshold_status' => $result['threshold_status'],
                ])->values()->all(),
            ];
        });
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function managementPriorities(User $principal, array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ])->validate();

        return $this->withPrincipalContext($principal, function () use ($principal, $validated): array {
            $permissions = $this->dataPermissions($principal);
            if (! ($permissions['task.index'] ?? false)) {
                return [
                    'availability' => 'unavailable_permission_denied',
                    'reason' => 'Task visibility permission is required to retrieve operational priorities.',
                    'as_of' => $this->reportingNow()->toIso8601String(),
                    'timezone' => app(KpiCatalogueRegistry::class)->timezone(),
                    'overdue_count' => null,
                    'overdue_tasks' => [],
                    'upcoming_tasks' => [],
                ];
            }

            $summary = app(TasksAndProjectsData::class)->summary(
                now: $this->reportingNow(),
                canViewTasks: true,
                canViewAllTasks: $permissions['task.view_all'],
                canViewProjects: $permissions['project.index'],
                canViewPhases: $permissions['project_phase.index'],
            );
            $limit = (int) ($validated['limit'] ?? 8);

            $mapTask = static fn ($task): array => [
                'action' => (string) $task->action,
                'status' => (string) $task->status,
                'due_date' => $task->due_date?->toDateString(),
                'project' => $permissions['project.index'] ? $task->project?->title : null,
                'phase' => $permissions['project_phase.index'] ? $task->projectPhase?->title : null,
            ];

            return [
                'availability' => 'available',
                'as_of' => $this->reportingNow()->toIso8601String(),
                'timezone' => app(KpiCatalogueRegistry::class)->timezone(),
                'priority_basis' => 'Due-date ordering only. The application does not define a universal task priority score.',
                'overdue_count' => $summary['tasks_overdue'],
                'overdue_tasks' => $summary['overdue_tasks']->take($limit)->map($mapTask)->values()->all(),
                'upcoming_tasks' => $summary['upcoming_tasks']->take($limit)->map($mapTask)->values()->all(),
            ];
        });
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function periodComparison(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'current_period' => ['required', 'array'],
            'current_period.start_date' => ['required', 'date_format:Y-m-d'],
            'current_period.end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:current_period.start_date'],
            'comparison_period' => ['required', 'array'],
            'comparison_period.start_date' => ['required', 'date_format:Y-m-d'],
            'comparison_period.end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:comparison_period.start_date'],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ])->validate();

        $timezone = (string) ($validated['timezone'] ?? app(KpiCatalogueRegistry::class)->timezone());

        return [
            'availability' => 'unavailable_historical_period_not_supported',
            'reason' => 'KPI catalogue v1 supports daily as-of snapshots only. Historical point-in-time reconstruction and date-range aggregation are not implemented.',
            'catalogue_version' => app(KpiCatalogueRegistry::class)->version(),
            'supported_period' => [
                'kind' => 'as_of',
                'granularity' => 'day',
                'historical' => false,
            ],
            'current_period' => $validated['current_period'],
            'comparison_period' => $validated['comparison_period'],
            'timezone' => $timezone,
            'results' => [],
        ];
    }

    /** @param array<string, mixed> $catalogue
     * @return array<string, mixed>
     */
    private function safeKpiCatalogue(array $catalogue): array
    {
        return [
            'status' => $catalogue['status'] ?? 'available',
            'catalogue_version' => $catalogue['catalogue_version'],
            'catalogue_approval_status' => $catalogue['catalogue_approval_status'],
            'as_of' => $catalogue['as_of'],
            'timezone' => $catalogue['timezone'],
            'results' => collect($catalogue['results'])->map(static fn (array $result): array => [
                'key' => $result['key'],
                'label' => $result['label'],
                'domain' => $result['domain'],
                'unit' => $result['unit'],
                'value' => $result['value'],
                'state' => $result['state'],
                'availability' => $result['availability'],
                'as_of' => $result['as_of'],
                'period' => $result['period'],
                'freshness' => $result['freshness'],
                'source' => $result['source'],
                'provenance' => [
                    'source_model' => data_get($result, 'provenance.source_model'),
                    'calculation_service' => data_get($result, 'provenance.calculation_service'),
                    'calculation_type' => data_get($result, 'provenance.calculation_type'),
                    'formula' => data_get($result, 'provenance.formula'),
                    'definition_version' => data_get($result, 'provenance.definition_version'),
                ],
                'formula' => $result['formula'],
                'missing_data' => $result['missing_data'],
                'approval_status' => $result['approval_status'],
                'unavailable_reason' => $result['unavailable_reason'],
                'threshold_status' => $result['threshold_status'],
                'target_value' => $result['target_value'],
                'warning_threshold' => $result['warning_threshold'],
                'critical_threshold' => $result['critical_threshold'],
                'definition' => $result['definition'],
            ])->values()->all(),
        ];
    }

    /** @return array<string, bool> */
    private function dataPermissions(User $principal): array
    {
        $permissions = [];
        foreach (self::DATA_PERMISSIONS as $permission) {
            $permissions[$permission] = $this->authorization->hasDomainPermission($principal, $permission);
        }

        return $permissions;
    }

    private function reportingNow(): CarbonImmutable
    {
        return CarbonImmutable::now(app(KpiCatalogueRegistry::class)->timezone());
    }

    /** @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function withPrincipalContext(User $principal, callable $callback): mixed
    {
        $previousGuard = Auth::getDefaultDriver();
        $domainGuard = (string) config('mcp.domain_permission_guard', config('auth.defaults.guard', 'web'));
        $guard = Auth::guard($domainGuard);
        $previousUser = $guard->user();
        $guard->setUser($principal);
        Auth::shouldUse($domainGuard);

        try {
            return $callback();
        } finally {
            if ($previousUser instanceof Authenticatable) {
                $guard->setUser($previousUser);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            } else {
                Auth::forgetGuards();
            }
            Auth::shouldUse($previousGuard);
        }
    }

    /** @return array<string, mixed> */
    private function emptyInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass,
            'additionalProperties' => false,
        ];
    }
}
