<?php

namespace Tests\Feature;

use App\Filament\Pages\OverviewDashboard;
use App\Filament\Resources\KpiAlerts\KpiAlertResource;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\KpiAlert;
use App\Models\KpiDefinition;
use App\Models\User;
use App\Services\AdminOverviewDashboardData;
use App\Services\KpiAggregationService;
use App\Services\KpiAlertService;
use App\Services\KpiDefinitionService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ExecutiveDashboardRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_dashboard_reconciles_synthetic_source_records_and_renders_authorized_drilldown(): void
    {
        $viewer = User::factory()->create();
        $this->assignPermissions($viewer, [
            'overview-dashboard.view',
            'customer.index',
            'customer_lead.index',
        ]);

        $customers = Customer::factory()->count(3)->create();
        $deletedCustomer = Customer::factory()->create();
        $deletedCustomer->delete();
        $unknownLead = CustomerLead::factory()->create([
            'source_category' => 'unknown',
            'converted_customer_id' => null,
            'status' => 'new',
        ]);
        $knownLead = CustomerLead::factory()->create([
            'source_category' => 'paid_search',
            'converted_customer_id' => null,
            'status' => 'qualified',
        ]);

        $response = $this->actingAs($viewer)->get('/admin');
        $response->assertSuccessful()
            ->assertSee('Customer records')
            ->assertSee('Unconverted lead records')
            ->assertSee('Leads with unknown source')
            ->assertSee('Source freshness: source freshness unknown')
            ->assertSee('Open source list');

        $dashboard = app(OverviewDashboard::class)->getDashboardSummary();
        $results = collect($dashboard['kpis']['results'])->keyBy('key');

        $this->assertSame(3, $results['customer_record_count']['value'], 'customer_record_count must equal non-deleted synthetic customers.');
        $this->assertSame(2, $results['unconverted_lead_count']['value'], 'unconverted_lead_count must equal leads without a customer link.');
        $this->assertSame(1, $results['unknown_lead_source_count']['value'], 'unknown_lead_source_count must count only the explicit unknown category.');
        $this->assertSame($customers->pluck('id')->sort()->values()->all(), $results['customer_record_count']['source_record_ids']);
        $this->assertSame([$unknownLead->id, $knownLead->id], $results['unconverted_lead_count']['source_record_ids']);
        $this->assertNotContains($deletedCustomer->id, $results['customer_record_count']['source_record_ids']);
        $this->assertNotNull($results['customer_record_count']['source_url']);
        $this->assertNotNull($results['unconverted_lead_count']['source_url']);
        $this->assertSame('source_freshness_unknown', $results['customer_record_count']['freshness']['status']);
        $this->assertSame('Europe/Bucharest', $results['customer_record_count']['period']['timezone']);
    }

    public function test_dashboard_does_not_expose_counts_source_ids_or_drilldown_without_source_permissions(): void
    {
        $viewer = User::factory()->create();
        $this->assignPermissions($viewer, ['overview-dashboard.view']);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($viewer)->get('/admin');
        $response->assertSuccessful()
            ->assertSee('No summary metrics are available for your current resource permissions.')
            ->assertSee('Unavailable')
            ->assertDontSee('Open source list')
            ->assertDontSee('View records')
            ->assertDontSee('Source record IDs');

        $dashboard = app(OverviewDashboard::class)->getDashboardSummary();
        $customerKpi = collect($dashboard['kpis']['results'])->firstWhere('key', 'customer_record_count');

        $this->assertSame('unavailable_permission_denied', $customerKpi['availability']);
        $this->assertNull($customerKpi['value']);
        $this->assertSame([], $customerKpi['source_record_ids']);
        $this->assertNull($customerKpi['source_url']);
    }

    public function test_empty_source_population_is_a_valid_zero_with_empty_provenance(): void
    {
        $viewer = User::factory()->create();
        $this->assignPermissions($viewer, ['overview-dashboard.view', 'customer.index']);

        $response = $this->actingAs($viewer)->get('/admin');
        $response->assertSuccessful()->assertSee('Customer records')->assertSee('0');

        $customerKpi = collect(app(OverviewDashboard::class)->getDashboardSummary()['kpis']['results'])
            ->firstWhere('key', 'customer_record_count');

        $this->assertSame('available', $customerKpi['state']);
        $this->assertSame(0, $customerKpi['value']);
        $this->assertSame(0, $customerKpi['included_count']);
        $this->assertSame([], $customerKpi['source_record_ids']);
        $this->assertFalse($customerKpi['source_record_ids_truncated']);
    }

    public function test_malformed_active_definition_is_reported_as_unavailable_without_breaking_dashboard(): void
    {
        $viewer = User::factory()->create();
        $this->assignPermissions($viewer, ['overview-dashboard.view', 'customer.index']);
        Customer::factory()->count(2)->create();
        $definition = $this->activeCustomerDefinition($viewer);

        KpiDefinition::query()->whereKey($definition->getKey())->update([
            'filters' => json_encode([['field' => 'unsupported_column', 'operator' => 'eq', 'value' => 'x']], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->actingAs($viewer)->get('/admin');
        $response->assertSuccessful()->assertSee('Unavailable');

        $summary = app(AdminOverviewDashboardData::class)->summary(['customer.index' => true]);
        $customerKpi = collect($summary['kpis']['results'])->firstWhere('key', 'customer_record_count');

        $this->assertSame('unavailable_invalid_definition', $customerKpi['availability']);
        $this->assertNull($customerKpi['value']);
        $this->assertSame([], $customerKpi['provenance']['source_record_ids']);
    }

    public function test_definition_aggregation_alert_and_evidence_detail_form_a_reproducible_synthetic_flow(): void
    {
        $owner = User::factory()->create();
        $this->assignPermissions($owner, [
            'overview-dashboard.view',
            'customer.index',
            'kpi_alert.view_own',
        ]);
        Gate::define('kpi_definition.create', fn (): bool => true);
        Gate::define('kpi_definition.approve', fn (): bool => true);
        $definition = $this->activeCustomerDefinition($owner, [
            'alerts_enabled' => true,
            'warning_threshold' => 2,
            'critical_threshold' => 4,
            'threshold_direction' => 'higher_is_worse',
            'alert_owner_id' => $owner->getKey(),
        ]);
        $customers = Customer::factory()->count(4)->create();

        $result = app(KpiAggregationService::class)->aggregate($definition, ['customer.index' => true]);
        $this->assertSame(4, $result['value']);
        $this->assertSame('critical', $result['threshold_status']);
        $this->assertSame($customers->pluck('id')->sort()->values()->all(), $result['provenance']['source_record_ids']);
        $this->assertSame('source_freshness_unknown', $result['freshness']['status']);

        $alerts = app(KpiAlertService::class);
        $this->assertNull($alerts->evaluate($definition, $result), 'Unknown freshness must suppress an otherwise critical result.');
        $this->assertDatabaseCount('kpi_alerts', 0);

        $result['freshness'] = ['status' => 'fresh', 'staleness_seconds' => 15];
        $alert = $alerts->evaluate($definition, $result);
        $this->assertInstanceOf(KpiAlert::class, $alert);
        $this->assertSame('critical', $alert->severity);
        $this->assertSame($customers->pluck('id')->sort()->values()->all(), $alert->evidence['source_record_ids']);

        $staleRecovery = $result;
        $staleRecovery['threshold_status'] = 'normal';
        $staleRecovery['freshness'] = ['status' => 'stale', 'staleness_seconds' => 90000];
        $this->assertNull($alerts->evaluate($definition, $staleRecovery));
        $this->assertSame(KpiAlert::STATUS_OPEN, $alert->fresh()->status, 'Stale data must not resolve an existing incident.');

        $permissionDenied = app(KpiAggregationService::class)->aggregate($definition, ['customer.index' => false]);
        $this->assertSame('unavailable_permission_denied', $permissionDenied['availability']);
        $this->assertNull($alerts->evaluate($definition, $permissionDenied));
        $this->assertSame(KpiAlert::STATUS_OPEN, $alert->fresh()->status, 'Permission-denied data must not resolve an existing incident.');

        $this->actingAs($owner)
            ->get(KpiAlertResource::getUrl('view', ['record' => $alert]))
            ->assertSuccessful()
            ->assertSee('customer_record_count')
            ->assertSee('source_record_ids')
            ->assertSee('definition_version');
    }

    public function test_large_source_set_keeps_exact_count_with_bounded_provenance_and_query_budget(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeCustomerDefinition($owner);
        $customers = Customer::factory()->count(250)->create();
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries++;
        });

        $result = app(KpiAggregationService::class)->aggregate($definition, ['customer.index' => true]);

        $this->assertSame(250, $result['value']);
        $this->assertSame(250, $result['included_count']);
        $this->assertCount(KpiAggregationService::MAX_PROVENANCE_RECORDS, $result['provenance']['source_record_ids']);
        $this->assertTrue($result['provenance']['source_record_ids_truncated']);
        $this->assertSame($customers->pluck('id')->sort()->take(KpiAggregationService::MAX_PROVENANCE_RECORDS)->values()->all(), $result['provenance']['source_record_ids']);
        $this->assertLessThanOrEqual(4, $queries, 'Count, provenance, and grouping queries must remain bounded independently of source row count.');
    }

    public function test_reporting_day_boundary_is_evaluated_in_catalogue_timezone(): void
    {
        $owner = User::factory()->create();
        $definition = $this->activeCustomerDefinition($owner);
        $customer = Customer::factory()->create();
        $service = app(KpiAggregationService::class);

        $beforeLocalMidnight = $service->aggregate(
            $definition,
            ['customer.index' => true],
            CarbonImmutable::parse('2026-10-26T21:59:59Z'),
        );
        $afterLocalMidnight = $service->aggregate(
            $definition,
            ['customer.index' => true],
            CarbonImmutable::parse('2026-10-26T22:00:00Z'),
        );

        $this->assertSame('2026-10-26', $beforeLocalMidnight['period']['start_date']);
        $this->assertSame('2026-10-27', $afterLocalMidnight['period']['start_date']);
        $this->assertSame('Europe/Bucharest', $afterLocalMidnight['period']['timezone']);
        $this->assertSame('inclusive_calendar_date', $afterLocalMidnight['period']['boundary']);
        $this->assertFalse($afterLocalMidnight['period']['historical']);
        $this->assertSame([$customer->id], $afterLocalMidnight['provenance']['source_record_ids']);
    }

    /** @param array<int, string> $permissions */
    private function assignPermissions(User $user, array $permissions): void
    {
        $role = HexaRole::query()->create([
            'name' => 'Executive dashboard regression '.$user->getKey(),
            'created_by_name' => 'Regression suite',
            'access' => ['Executive Dashboard' => $permissions],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());
    }

    /** @param array<string, mixed> $overrides */
    private function activeCustomerDefinition(User $owner, array $overrides = []): KpiDefinition
    {
        $this->assignPermissions($owner, ['kpi_definition.create', 'kpi_definition.approve']);
        $this->actingAs($owner);
        Gate::define('kpi_definition.create', fn (): bool => true);
        Gate::define('kpi_definition.approve', fn (): bool => true);
        $attributes = array_merge([
            'key' => 'customer_record_count',
            'effective_from' => CarbonImmutable::now('Europe/Bucharest')->toDateString(),
            'change_summary' => 'Create deterministic executive dashboard regression definition.',
        ], $overrides);

        $definition = app(KpiDefinitionService::class)->createDraft($attributes);

        return app(KpiDefinitionService::class)->activate($definition);
    }
}
