<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Services\KpiAggregationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveKpiCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_v1_contains_only_well_formed_allow_listed_definitions(): void
    {
        $catalogue = config('kpi_catalogue');
        $this->assertSame('v1', $catalogue['version']);
        $this->assertSame('Europe/Bucharest', $catalogue['timezone']);
        $this->assertSame('provisional', $catalogue['approval_status']);
        $this->assertNotEmpty($catalogue['definitions']);

        $allowedCalculationTypes = [
            'customer_record_count',
            'unconverted_lead_count',
            'unknown_lead_source_count',
            'open_task_count',
            'overdue_task_count',
            'competitor_offer_snapshot_count',
        ];
        foreach ($catalogue['definitions'] as $definition) {
            $key = $definition['key'];
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]+$/', $key);
            $this->assertContains($definition['calculation_type'], $allowedCalculationTypes);
            $this->assertNotSame('', trim($definition['label']));
            $this->assertNotSame('', trim($definition['source']));
            $this->assertNotSame('', trim($definition['source_service']));
            $this->assertNotSame('', trim($definition['calculation_service']));
            $this->assertNotSame('', trim($definition['formula']));
            $this->assertSame([], $definition['dimensions']);
            $this->assertSame(['kind' => 'as_of', 'granularity' => 'day', 'historical' => false], $definition['supported_period']);
            $this->assertNotSame('', trim($definition['permission']));
            $this->assertNotSame('', trim($definition['missing_data']));
            $this->assertNotSame('', trim($definition['description']));
            $this->assertContains($definition['unit'], ['records', 'snapshots']);
        }
    }

    public function test_service_returns_catalogue_results_and_uses_unavailable_not_zero_without_permission(): void
    {
        Customer::factory()->count(2)->create();
        CustomerLead::factory()->create(['source_category' => 'unknown', 'converted_customer_id' => null]);
        CustomerLead::factory()->create(['source_category' => 'paid_search', 'converted_customer_id' => null]);

        $results = app(KpiAggregationService::class)->current([
            'customer.index' => true,
            'customer_lead.index' => true,
        ], CarbonImmutable::parse('2026-10-27 12:00:00', 'Europe/Bucharest'));
        $byKey = collect($results['results'])->keyBy('key');

        $this->assertSame('v1', $results['catalogue_version']);
        $this->assertSame('provisional', $results['catalogue_approval_status']);
        $this->assertSame('2026-10-27', $results['as_of']);
        $this->assertSame(2, $byKey['customer_record_count']['value']);
        $this->assertSame(2, $byKey['unconverted_lead_count']['value']);
        $this->assertSame(1, $byKey['unknown_lead_source_count']['value']);
        $this->assertNull($byKey['open_task_count']['value']);
        $this->assertSame('unavailable', $byKey['open_task_count']['state']);
    }

    public function test_task_kpis_respect_viewer_scope_and_due_today_is_not_overdue(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($viewer);
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);

        Task::factory()->create(['project_id' => $project->id, 'project_phase_id' => $phase->id, 'assigned_to' => $viewer->id, 'created_by' => $viewer->id, 'status' => 'pending', 'due_date' => '2026-10-26']);
        Task::factory()->create(['project_id' => $project->id, 'project_phase_id' => $phase->id, 'assigned_to' => $viewer->id, 'created_by' => $viewer->id, 'status' => 'pending', 'due_date' => '2026-10-27']);
        auth()->logout();
        $this->actingAs($other);
        Task::factory()->create(['project_id' => $project->id, 'project_phase_id' => $phase->id, 'assigned_to' => $other->id, 'created_by' => $other->id, 'status' => 'pending', 'due_date' => '2026-10-25']);
        $this->actingAs($viewer);

        $results = app(KpiAggregationService::class)->current([
            'task.index' => true,
            'task.view_all' => false,
        ], CarbonImmutable::parse('2026-10-27 12:00:00', 'Europe/Bucharest'));
        $byKey = collect($results['results'])->keyBy('key');

        $this->assertSame(2, $byKey['open_task_count']['value']);
        $this->assertSame(1, $byKey['overdue_task_count']['value']);
    }

    public function test_as_of_period_boundary_uses_the_configured_reporting_timezone(): void
    {
        $viewer = User::factory()->create();
        $this->actingAs($viewer);
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);
        Task::factory()->create([
            'project_id' => $project->id,
            'project_phase_id' => $phase->id,
            'assigned_to' => $viewer->id,
            'created_by' => $viewer->id,
            'status' => 'pending',
            'due_date' => '2026-10-26',
        ]);

        $beforeLocalMidnight = app(KpiAggregationService::class)->current([
            'task.index' => true,
            'task.view_all' => false,
        ], CarbonImmutable::parse('2026-10-26T21:59:59Z'));
        $afterLocalMidnight = app(KpiAggregationService::class)->current([
            'task.index' => true,
            'task.view_all' => false,
        ], CarbonImmutable::parse('2026-10-26T22:00:00Z'));

        $before = collect($beforeLocalMidnight['results'])->firstWhere('key', 'overdue_task_count');
        $after = collect($afterLocalMidnight['results'])->firstWhere('key', 'overdue_task_count');
        $this->assertSame('2026-10-26', $beforeLocalMidnight['as_of']);
        $this->assertSame('2026-10-27', $afterLocalMidnight['as_of']);
        $this->assertSame(0, $before['value']);
        $this->assertSame(1, $after['value']);
    }

    public function test_catalogue_does_not_define_unapproved_financial_or_lifetime_metrics(): void
    {
        $keys = array_column(config('kpi_catalogue.definitions'), 'key');
        $this->assertNotContains('cac', $keys);
        $this->assertNotContains('customer_lifetime_value', $keys);
        $this->assertNotContains('revenue', $keys);
        $this->assertNotContains('profit_margin', $keys);
    }
}
