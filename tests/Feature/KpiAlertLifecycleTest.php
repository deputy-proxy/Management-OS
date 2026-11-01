<?php

namespace Tests\Feature;

use App\Contracts\KpiAlertDeliveryAdapter;
use App\Filament\Resources\KpiAlerts\KpiAlertResource;
use App\Models\Customer;
use App\Models\KpiAlert;
use App\Models\KpiAlertEvent;
use App\Models\KpiDefinition;
use App\Models\User;
use App\Services\KpiAggregationService;
use App\Services\KpiAlertService;
use App\Services\KpiDefinitionService;
use Carbon\CarbonImmutable;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class KpiAlertLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_warning_and_critical_boundaries_create_one_deduplicated_incident(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeDefinition($owner);
        Customer::factory()->count(10)->create();
        $service = app(KpiAlertService::class);

        $warning = app(KpiAggregationService::class)->aggregate($definition, ['customer.index' => true]);
        $this->assertSame(10, $warning['value']);
        $this->assertSame('warning', $warning['threshold_status']);
        $warning['freshness'] = ['status' => 'fresh', 'staleness_seconds' => 30];
        $alert = $service->evaluate($definition, $warning, CarbonImmutable::parse('2026-11-01T10:00:00Z'));
        $this->assertInstanceOf(KpiAlert::class, $alert);
        $this->assertSame('warning', $alert->severity);
        $this->assertSame(10.0, (float) $alert->observed_value);
        $this->assertSame('customer_record_count', $alert->evidence['kpi_key']);
        $this->assertSame($definition->version, $alert->evidence['definition_version']);
        $this->assertCount(10, $alert->evidence['source_record_ids']);
        $this->assertSame(route('filament.admin.general.resources.kpi-alerts.view', ['record' => $alert->id]), $alert->evidence_url);

        Customer::factory()->count(10)->create();
        $critical = app(KpiAggregationService::class)->aggregate($definition, ['customer.index' => true]);
        $this->assertSame(20, $critical['value']);
        $this->assertSame('critical', $critical['threshold_status']);
        $critical['freshness'] = ['status' => 'fresh', 'staleness_seconds' => 30];
        $updated = $service->evaluate($definition, $critical, CarbonImmutable::parse('2026-11-01T10:05:00Z'));

        $this->assertSame($alert->id, $updated?->id);
        $this->assertSame('critical', $updated?->severity);
        $this->assertSame(1, KpiAlert::query()->count());
        $this->assertSame(['raised', 'severity_changed'], KpiAlertEvent::query()->pluck('event')->all());
    }

    public function test_unavailable_invalid_unknown_and_stale_results_are_suppressed(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeDefinition($owner);
        $service = app(KpiAlertService::class);
        $base = $this->freshResult($definition, 10);
        $cases = [
            array_merge($base, ['availability' => 'unavailable_permission_denied']),
            array_merge($base, ['threshold_status' => 'unavailable']),
            array_merge($base, ['freshness' => ['status' => 'source_freshness_unknown', 'staleness_seconds' => null]]),
            array_merge($base, ['freshness' => ['status' => 'stale', 'staleness_seconds' => 90000]]),
            array_merge($base, ['freshness' => ['status' => 'fresh', 'staleness_seconds' => 90000]]),
        ];

        foreach ($cases as $case) {
            $this->assertNull($service->evaluate($definition, $case));
        }
        $this->assertDatabaseCount('kpi_alerts', 0);
    }

    public function test_normal_result_resolves_alert_and_cooldown_blocks_reopening(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeDefinition($owner, 30);
        $service = app(KpiAlertService::class);
        $start = CarbonImmutable::parse('2026-11-01T10:00:00Z');
        $alert = $service->evaluate($definition, $this->freshResult($definition, 10), $start);
        $this->assertSame(KpiAlert::STATUS_OPEN, $alert?->status);

        $staleRecovery = $this->freshResult($definition, 0, 'normal');
        $staleRecovery['freshness'] = ['status' => 'stale', 'staleness_seconds' => 90000];
        $service->evaluate($definition, $staleRecovery, $start->addMinute());
        $this->assertSame(KpiAlert::STATUS_OPEN, $alert?->fresh()?->status);

        $service->evaluate($definition, $this->freshResult($definition, 0, 'normal'), $start->addMinutes(2));
        $resolved = $alert?->fresh();
        $this->assertSame(KpiAlert::STATUS_RESOLVED, $resolved?->status);
        $this->assertNotNull($resolved?->cooldown_until);
        $this->assertSame(30, (int) $resolved->resolved_at->diffInMinutes($resolved->cooldown_until));

        $duringCooldown = $service->evaluate($definition, $this->freshResult($definition, 10), $start->addMinutes(10));
        $this->assertNull($duringCooldown);
        $afterCooldown = $service->evaluate($definition, $this->freshResult($definition, 10), $start->addMinutes(33));
        $this->assertInstanceOf(KpiAlert::class, $afterCooldown);
        $this->assertNotSame($alert?->id, $afterCooldown?->id);
        $this->assertSame(2, KpiAlert::query()->count());
    }

    public function test_acknowledgement_and_resolution_require_management_permission_and_are_audited(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $definition = $this->activeDefinition($owner);
        $service = app(KpiAlertService::class);
        $alert = $service->evaluate($definition, $this->freshResult($definition, 10));
        $this->assertInstanceOf(KpiAlert::class, $alert);

        $role = HexaRole::query()->create(['name' => 'KPI alert owner '.$owner->id, 'access' => ['KPI Alerts' => ['customer.index']], 'guard' => 'web']);
        $owner->roles()->attach($role->getKey());
        $this->actingAs($owner->fresh());
        try {
            $service->acknowledge($alert);
            $this->fail('Expected management permission to be enforced.');
        } catch (AuthorizationException) {
            $this->assertSame(KpiAlert::STATUS_OPEN, $alert->fresh()?->status);
        }

        $role->update(['access' => ['KPI Alerts' => ['customer.index', 'kpi_alert.manage_own']]]);
        $this->actingAs($owner->fresh());
        $acknowledged = $service->acknowledge($alert->fresh());
        $this->assertSame(KpiAlert::STATUS_ACKNOWLEDGED, $acknowledged->status);
        $this->assertSame($owner->id, $acknowledged->acknowledged_by);

        $resolved = $service->resolve($acknowledged);
        $this->assertSame(KpiAlert::STATUS_RESOLVED, $resolved->status);
        $this->assertSame($owner->id, $resolved->resolved_by);
        $this->assertSame(['raised', 'acknowledged', 'manually_resolved'], KpiAlertEvent::query()->pluck('event')->all());
    }

    public function test_delivery_is_disabled_by_default(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeDefinition($owner);
        $adapter = Mockery::mock(KpiAlertDeliveryAdapter::class);
        $this->app->instance(KpiAlertDeliveryAdapter::class, $adapter);
        config(['kpi_alerts.delivery_enabled' => false]);
        $adapter->shouldNotReceive('deliver');
        app(KpiAlertService::class)->evaluate($definition, $this->freshResult($definition, 10));
    }

    public function test_delivery_requires_the_owner_notification_permission_when_enabled(): void
    {
        $owner = User::factory()->create();
        $role = HexaRole::query()->create(['name' => 'KPI alert owner '.$owner->id, 'access' => ['KPI Alerts' => ['customer.index']], 'guard' => 'web']);
        $owner->roles()->attach($role->getKey());
        $definition = $this->activeDefinition($owner);
        $adapter = Mockery::mock(KpiAlertDeliveryAdapter::class);
        $this->app->instance(KpiAlertDeliveryAdapter::class, $adapter);
        config(['kpi_alerts.delivery_enabled' => true]);
        $adapter->shouldNotReceive('deliver');
        app(KpiAlertService::class)->evaluate($definition, $this->freshResult($definition, 10));
    }

    public function test_delivery_adapter_is_called_only_for_an_authorized_owner_when_enabled(): void
    {
        $owner = User::factory()->create();
        $role = HexaRole::query()->create(['name' => 'KPI alert notifier '.$owner->id, 'access' => ['KPI Alerts' => ['customer.index', 'kpi_alert.notify']], 'guard' => 'web']);
        $owner->roles()->attach($role->getKey());
        $definition = $this->activeDefinition($owner);
        $adapter = Mockery::mock(KpiAlertDeliveryAdapter::class);
        $this->app->instance(KpiAlertDeliveryAdapter::class, $adapter);
        config(['kpi_alerts.delivery_enabled' => true]);
        $adapter->shouldReceive('deliver')->once()->withArgs(fn (User $recipient, KpiAlert $alert, string $event): bool => $recipient->is($owner) && $alert->severity === 'warning' && $event === 'raised');

        app(KpiAlertService::class)->evaluate($definition, $this->freshResult($definition, 10));
    }

    public function test_lower_is_worse_critical_boundary_uses_the_registered_threshold_direction(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Gate::define('kpi_definition.create', fn (): bool => true);
        Gate::define('kpi_definition.approve', fn (): bool => true);
        $definition = app(KpiDefinitionService::class)->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
            'threshold_direction' => 'lower_is_worse',
            'warning_threshold' => 10,
            'critical_threshold' => 5,
            'alerts_enabled' => true,
            'alert_owner_id' => $owner->getKey(),
            'change_summary' => 'Configure a lower-is-worse synthetic threshold.',
        ]);
        $definition = app(KpiDefinitionService::class)->activate($definition);
        Customer::factory()->count(5)->create();
        $result = app(KpiAggregationService::class)->aggregate($definition, ['customer.index' => true]);
        $this->assertSame('critical', $result['threshold_status']);
        $result['freshness'] = ['status' => 'fresh', 'staleness_seconds' => 10];

        $alert = app(KpiAlertService::class)->evaluate($definition, $result);

        $this->assertSame('critical', $alert?->severity);
        $this->assertSame(5.0, (float) $alert?->trigger_threshold);
    }

    public function test_alert_visibility_is_limited_to_owned_incidents_without_global_view_permission(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeDefinition($owner);
        $alert = app(KpiAlertService::class)->evaluate($definition, $this->freshResult($definition, 10));
        $this->assertInstanceOf(KpiAlert::class, $alert);

        $other = User::factory()->create();
        KpiAlert::query()->create([
            'kpi_definition_id' => $definition->getKey(),
            'owner_id' => $other->getKey(),
            'dedupe_key' => 'customer_record_count:other-owner',
            'severity' => 'warning',
            'status' => KpiAlert::STATUS_OPEN,
            'observed_value' => 10,
            'trigger_threshold' => 10,
            'evidence' => ['kpi_key' => 'customer_record_count'],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $role = HexaRole::query()->create(['name' => 'KPI alert viewer '.$other->id, 'access' => ['KPI Alerts' => ['kpi_alert.view_own']], 'guard' => 'web']);
        $other->roles()->attach($role->getKey());
        $this->actingAs($other->fresh());

        $visible = app(KpiAlertService::class)->queryForCurrentUser()->get();
        $this->assertCount(1, $visible);
        $this->assertSame($other->id, $visible->first()->owner_id);

        $unprivileged = User::factory()->create();
        $deniedRole = HexaRole::query()->create(['name' => 'KPI alert non-viewer '.$unprivileged->id, 'access' => ['KPI Alerts' => ['customer.index']], 'guard' => 'web']);
        $unprivileged->roles()->attach($deniedRole->getKey());
        $this->actingAs($unprivileged->fresh());
        $this->expectException(AuthorizationException::class);
        app(KpiAlertService::class)->queryForCurrentUser();
    }

    public function test_retiring_a_rule_resolves_its_open_incident_with_a_lifecycle_event(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeDefinition($owner);
        $alert = app(KpiAlertService::class)->evaluate($definition, $this->freshResult($definition, 10));
        $this->assertInstanceOf(KpiAlert::class, $alert);

        Gate::define('kpi_definition.approve', fn (): bool => true);
        app(KpiDefinitionService::class)->retire($definition->fresh());
        app(KpiAlertService::class)->evaluateConfiguredAlerts();

        $this->assertSame(KpiAlert::STATUS_RESOLVED, $alert->fresh()?->status);
        $this->assertSame(['raised', 'rule_deactivated'], $alert->events()->pluck('event')->all());
    }

    public function test_alert_detail_page_exposes_versioned_source_evidence_to_an_authorized_owner(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeDefinition($owner);
        $alert = app(KpiAlertService::class)->evaluate($definition, $this->freshResult($definition, 10));
        $this->assertInstanceOf(KpiAlert::class, $alert);
        $role = HexaRole::query()->create(['name' => 'KPI alert detail viewer '.$owner->id, 'access' => ['KPI Alerts' => ['kpi_alert.view_own']], 'guard' => 'web']);
        $owner->roles()->attach($role->getKey());

        $this->actingAs($owner->fresh())
            ->get(KpiAlertResource::getUrl('view', ['record' => $alert]))
            ->assertSuccessful()
            ->assertSee('customer_record_count')
            ->assertSee('source_record_ids')
            ->assertSee('definition_version');
    }

    public function test_alert_lifecycle_records_are_append_only_and_alerts_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeDefinition($owner);
        $alert = app(KpiAlertService::class)->evaluate($definition, $this->freshResult($definition, 10));
        $event = $alert?->events()->firstOrFail();
        $this->assertInstanceOf(KpiAlertEvent::class, $event);

        try {
            $event->update(['event' => 'tampered']);
            $this->fail('Expected KPI alert lifecycle history to be immutable.');
        } catch (\LogicException) {
            $this->assertSame('raised', $event->fresh()->event);
        }

        $this->expectException(\LogicException::class);
        $alert->delete();
    }

    public function test_evaluation_command_runs_without_an_authenticated_web_session(): void
    {
        $this->artisan('kpi:alerts-evaluate')
            ->expectsOutput('Evaluated 0 configured KPI alert rules.')
            ->assertExitCode(0);
    }

    public function test_alert_configuration_requires_direction_threshold_and_owner(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Gate::define('kpi_definition.create', fn (): bool => true);
        $this->expectException(ValidationException::class);
        app(KpiDefinitionService::class)->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
            'alerts_enabled' => true,
            'threshold_direction' => 'higher_is_worse',
            'warning_threshold' => 10,
            'alert_owner_id' => null,
            'change_summary' => 'Invalid alert rule without an owner.',
        ]);
    }

    private function activeDefinition(User $owner, int $cooldown = 60): KpiDefinition
    {
        $this->actingAs($owner);
        Gate::define('kpi_definition.create', fn (): bool => true);
        Gate::define('kpi_definition.approve', fn (): bool => true);
        $definition = app(KpiDefinitionService::class)->createDraft([
            'key' => 'customer_record_count',
            'effective_from' => Carbon::tomorrow('Europe/Bucharest')->toDateString(),
            'threshold_direction' => 'higher_is_worse',
            'warning_threshold' => 10,
            'critical_threshold' => 20,
            'alerts_enabled' => true,
            'alert_cooldown_minutes' => $cooldown,
            'alert_owner_id' => $owner->getKey(),
            'change_summary' => 'Configure provisional threshold alert rule.',
        ]);

        return app(KpiDefinitionService::class)->activate($definition);
    }

    /** @return array<string, mixed> */
    private function freshResult(KpiDefinition $definition, int $value, ?string $status = null): array
    {
        $result = app(KpiAggregationService::class)->aggregate($definition, ['customer.index' => true]);
        $result['value'] = $value;
        $result['availability'] = 'available';
        $result['threshold_status'] = $status ?? ($value >= 20 ? 'critical' : ($value >= 10 ? 'warning' : 'normal'));
        $result['freshness'] = ['status' => 'fresh', 'staleness_seconds' => 30];
        $result['provenance'] = ['source_record_ids' => [1, 2, 3]];

        return $result;
    }
}
