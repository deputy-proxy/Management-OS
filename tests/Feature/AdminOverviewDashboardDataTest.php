<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Student;
use App\Models\Task;
use App\Models\User;
use App\Services\AdminOverviewDashboardData;
use App\Services\KpiAggregationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminOverviewDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_only_counts_resources_the_viewer_can_access_and_excludes_soft_deleted_records(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Student::factory()->count(2)->create();
        Student::factory()->create()->delete();
        Employee::factory()->create();
        Customer::factory()->create();

        $summary = app(AdminOverviewDashboardData::class)->summary([
            'student.index' => true,
            'employee.index' => false,
            'customer.index' => true,
        ], CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone')));

        $this->assertSame(['Student records', 'Customers'], array_column($summary['metrics'], 'label'));
        $this->assertSame([2, 4], array_column($summary['metrics'], 'value'));
        // Student fixtures create their own customer records; all are visible under customer.index.
        $this->assertSame(config('app.timezone'), $summary['timezone']);
        $this->assertSame('October 18, 2026', $summary['as_of']);

        $customerKpi = collect($summary['kpis']['results'])->firstWhere('key', 'customer_record_count');
        $this->assertSame('provisional', $summary['kpis']['catalogue_approval_status']);
        $this->assertSame(Customer::class, $customerKpi['source_service']);
        $this->assertSame('customer_record_count', $customerKpi['calculation_type']);
        $this->assertSame('COUNT(customers.id) WHERE customers.deleted_at IS NULL', $customerKpi['formula']);
        $this->assertSame([], $customerKpi['dimensions']);
        $this->assertSame(['kind' => 'as_of', 'granularity' => 'day', 'historical' => false], $customerKpi['supported_period']);
    }

    public function test_overdue_tasks_follow_the_existing_assigned_or_created_visibility_scope(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $project = Project::factory()->create();

        Task::factory()->create([
            'action' => 'Visible overdue task', 'assigned_to' => $user->id, 'created_by' => $other->id,
            'project_id' => $project->id, 'due_date' => '2026-10-17', 'status' => 'pending',
        ]);
        $this->actingAs($other);
        Task::factory()->create([
            'action' => 'Hidden overdue task', 'assigned_to' => $other->id,
            'project_id' => $project->id, 'due_date' => '2026-10-16', 'status' => 'pending',
        ]);
        $this->actingAs($user);
        Task::factory()->create([
            'action' => 'Completed task', 'assigned_to' => $user->id, 'created_by' => $user->id,
            'project_id' => $project->id, 'due_date' => '2026-10-16', 'status' => 'done',
        ]);

        $summary = app(AdminOverviewDashboardData::class)->summary([
            'task.index' => true,
            'task.view_all' => false,
        ], CarbonImmutable::parse('2026-10-18 12:00:00', config('app.timezone')));

        $this->assertSame(1, $summary['metrics'][0]['value']);
        $this->assertSame(['Visible overdue task'], $summary['overdue_tasks']->pluck('action')->all());
    }

    public function test_recent_activity_is_limited_to_permitted_resource_types_and_exposes_no_properties(): void
    {
        $student = Student::factory()->create();
        $customer = Customer::factory()->create();
        Activity::create([
            'description' => 'Student updated', 'subject_type' => Student::class,
            'subject_id' => $student->id, 'properties' => ['secret' => 'not shown'],
        ]);
        Activity::create([
            'description' => 'Customer updated', 'subject_type' => Customer::class,
            'subject_id' => $customer->id,
        ]);

        $summary = app(AdminOverviewDashboardData::class)->summary(['student.index' => true]);

        $this->assertTrue($summary['activity_available']);
        $this->assertSame(['Student updated'], $summary['recent_activity']->pluck('description')->all());
        $this->assertArrayNotHasKey('properties', $summary['recent_activity']->first()->getAttributes());
    }

    public function test_kpi_aggregation_failure_returns_a_dashboard_error_state_without_breaking_summary(): void
    {
        $aggregation = \Mockery::mock(KpiAggregationService::class);
        $aggregation->shouldReceive('current')->once()->andThrow(new \RuntimeException('Synthetic aggregation failure.'));
        app()->instance(KpiAggregationService::class, $aggregation);

        $summary = app(AdminOverviewDashboardData::class)->summary([]);

        $this->assertSame('error', $summary['kpis']['status']);
        $this->assertSame([], $summary['kpis']['results']);
        $this->assertSame([], $summary['metrics']);
        $this->assertFalse($summary['activity_available']);
    }

    public function test_no_metrics_or_activity_are_returned_without_resource_permissions(): void
    {
        $summary = app(AdminOverviewDashboardData::class)->summary([]);

        $this->assertSame([], $summary['metrics']);
        $this->assertFalse($summary['activity_available']);
        $this->assertCount(0, $summary['recent_activity']);
        $this->assertCount(0, $summary['overdue_tasks']);
    }
}
