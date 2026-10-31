<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Models\WorkerEngagement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkerEngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unresolved_arrangement_can_be_recorded_without_inventing_a_worker_classification(): void
    {
        $engagement = WorkerEngagement::query()->create([
            'engagement_type' => 'unknown',
            'status' => 'unresolved',
            'source_reference' => 'synthetic-reference-001',
        ]);

        $this->assertNull($engagement->employee_id);
        $this->assertNull($engagement->vendor_id);
        $this->assertSame('unknown', $engagement->fresh()->engagement_type);
    }

    public function test_employee_engagement_links_to_its_effective_dated_contract(): void
    {
        $employee = Employee::factory()->create();
        $contract = EmployeeContract::factory()->create(['employee_id' => $employee->id]);

        $engagement = WorkerEngagement::query()->create([
            'employee_id' => $employee->id,
            'employee_contract_id' => $contract->id,
            'engagement_type' => 'employee',
            'status' => 'active',
            'start_date' => '2026-01-01',
        ]);

        $this->assertTrue($engagement->employee->is($employee));
        $this->assertTrue($engagement->employeeContract->is($contract));
        $this->assertCount(1, $employee->engagements);
    }

    public function test_employee_type_cannot_be_saved_without_employee_link(): void
    {
        $this->expectException(ValidationException::class);

        WorkerEngagement::query()->create([
            'engagement_type' => 'employee',
            'status' => 'active',
            'start_date' => '2026-01-01',
        ]);
    }

    public function test_pfa_engagement_links_to_vendor_contract_without_using_employee_payroll_records(): void
    {
        $vendor = Vendor::factory()->create();
        $contract = VendorContract::factory()->create(['vendor_id' => $vendor->id]);

        $engagement = WorkerEngagement::query()->create([
            'vendor_id' => $vendor->id,
            'vendor_contract_id' => $contract->id,
            'engagement_type' => 'pfa_contractor',
            'status' => 'active',
            'start_date' => '2026-01-01',
        ]);

        $this->assertTrue($engagement->vendor->is($vendor));
        $this->assertTrue($engagement->vendorContract->is($contract));
        $this->assertNull($engagement->employee_id);
    }

    public function test_engagement_cannot_link_both_employee_and_vendor(): void
    {
        $this->expectException(ValidationException::class);

        WorkerEngagement::query()->create([
            'employee_id' => Employee::factory()->create()->id,
            'vendor_id' => Vendor::factory()->create()->id,
            'engagement_type' => 'other',
            'status' => 'unresolved',
        ]);
    }

    public function test_employee_contract_must_belong_to_the_selected_employee(): void
    {
        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();
        $contract = EmployeeContract::factory()->create(['employee_id' => $otherEmployee->id]);

        $this->expectException(ValidationException::class);

        WorkerEngagement::query()->create([
            'employee_id' => $employee->id,
            'employee_contract_id' => $contract->id,
            'engagement_type' => 'employee',
            'status' => 'active',
        ]);
    }

    public function test_vendor_contract_must_belong_to_the_selected_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        $otherVendor = Vendor::factory()->create();
        $contract = VendorContract::factory()->create(['vendor_id' => $otherVendor->id]);

        $this->expectException(ValidationException::class);

        WorkerEngagement::query()->create([
            'vendor_id' => $vendor->id,
            'vendor_contract_id' => $contract->id,
            'engagement_type' => 'pfa_contractor',
            'status' => 'active',
        ]);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->expectException(ValidationException::class);

        WorkerEngagement::query()->create([
            'engagement_type' => 'unknown',
            'status' => 'unresolved',
            'start_date' => '2026-03-01',
            'end_date' => '2026-02-28',
        ]);
    }

    public function test_current_scope_uses_status_and_effective_dates(): void
    {
        WorkerEngagement::factory()->create([
            'engagement_type' => 'unknown',
            'status' => 'active',
            'start_date' => '2026-01-01',
        ]);
        WorkerEngagement::factory()->create([
            'engagement_type' => 'unknown',
            'status' => 'active',
            'start_date' => '2026-12-01',
        ]);
        WorkerEngagement::factory()->create([
            'engagement_type' => 'unknown',
            'status' => 'ended',
            'start_date' => '2026-01-01',
        ]);

        $this->assertSame(1, WorkerEngagement::query()->current('2026-06-01')->count());
    }

    public function test_linked_records_are_protected_from_hard_deletion(): void
    {
        $employee = Employee::factory()->create();
        WorkerEngagement::query()->create([
            'employee_id' => $employee->id,
            'engagement_type' => 'employee',
            'status' => 'active',
        ]);

        try {
            $employee->forceDelete();
            $this->fail('Hard deleting a linked employee should be blocked to preserve engagement history.');
        } catch (QueryException) {
            $this->assertDatabaseHas('worker_engagements', ['employee_id' => $employee->id]);
        }
    }
}
