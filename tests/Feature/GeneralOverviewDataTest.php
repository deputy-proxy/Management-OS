<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Product;
use App\Models\Session;
use App\Services\GeneralOverviewData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeneralOverviewDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_presence_counts_distinct_employees_with_an_active_timesheet(): void
    {
        $now = CarbonImmutable::parse('2026-10-14 10:30:00', config('app.timezone'));
        $employee = Employee::factory()->create(['full_name' => 'Present Employee']);
        $this->insertTimesheet($employee->id, $now, '09:00:00', '11:00:00');
        $this->insertTimesheet($employee->id, $now, '10:00:00', '12:00:00');

        $ended = Employee::factory()->create(['full_name' => 'Finished Employee']);
        $this->insertTimesheet($ended->id, $now, '08:00:00', '10:00:00');

        $this->assertSame(
            ['Present Employee'],
            app(GeneralOverviewData::class)->employeesPresent($now)->pluck('full_name')->all(),
        );
    }

    public function test_average_course_margin_uses_current_month_complete_courses_only(): void
    {
        $now = CarbonImmutable::parse('2026-10-14 10:30:00', config('app.timezone'));
        $courseA = Course::factory()->create();
        $courseB = Course::factory()->create();
        $courseWithoutCosts = Course::factory()->create();
        $productA = $this->courseProduct($courseA);
        $productB = $this->courseProduct($courseB);
        $productC = $this->courseProduct($courseWithoutCosts);

        $this->insertRevenue($productA->id, '2026-10-01', 200);
        $this->insertCost($productA->id, '2026-10-01', 100);
        $this->insertRevenue($productB->id, '2026-10-01', 100);
        $this->insertCost($productB->id, '2026-10-01', 75);
        $this->insertRevenue($productC->id, '2026-10-01', 100);
        $this->insertRevenue($productA->id, '2026-09-01', 1000);
        $this->insertCost($productA->id, '2026-09-01', 0);

        $result = app(GeneralOverviewData::class)->averageCourseMargin($now);

        $this->assertSame(37.5, $result['value']);
        $this->assertSame(2, $result['course_count']);
        $this->assertSame('October 2026', $result['month']);
    }

    public function test_average_feedback_ignores_other_months_and_handles_empty_data(): void
    {
        $now = CarbonImmutable::parse('2026-10-14 10:30:00', config('app.timezone'));
        CustomerInteraction::factory()->create(['timestamp' => '2026-10-02 10:00:00', 'interaction_rating' => 3]);
        CustomerInteraction::factory()->create(['timestamp' => '2026-10-03 10:00:00', 'interaction_rating' => 5]);
        CustomerInteraction::factory()->create(['timestamp' => '2026-09-30 23:59:59', 'interaction_rating' => 1]);

        $result = app(GeneralOverviewData::class)->averageFeedback($now);

        $this->assertSame(4.0, $result['value']);
        $this->assertSame(2, $result['response_count']);

        CustomerInteraction::query()->delete();
        $empty = app(GeneralOverviewData::class)->averageFeedback($now);
        $this->assertNull($empty['value']);
        $this->assertSame(0, $empty['response_count']);
    }

    public function test_weekly_schedule_merges_both_session_types_in_start_time_order(): void
    {
        $now = CarbonImmutable::parse('2026-10-14 10:30:00', config('app.timezone'));
        $course = Course::factory()->create(['title' => 'Test Course']);
        $employee = Employee::factory()->create(['full_name' => 'Teacher']);
        $customer = Customer::factory()->create(['name' => 'Customer']);
        $group = Group::factory()->create(['course_id' => $course->id, 'teacher_id' => $employee->id, 'title' => 'Group A']);

        Session::factory()->create([
            'session_type' => 'group',
            'group_id' => $group->id,
            'course_id' => $course->id,
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-14 12:00:00',
            'ends_at' => '2026-10-14 13:00:00',
            'status' => 'scheduled',
            'identifier' => 'G-1',
        ]);

        Session::factory()->individual()->create([
            'identifier' => 'I-1',
            'customer_id' => $customer->id,
            'course_id' => $course->id,
            'teacher_id' => $employee->id,
            'starts_at' => '2026-10-14 11:00:00',
            'ends_at' => '2026-10-14 12:00:00',
            'status' => 'scheduled',
        ]);

        $result = app(GeneralOverviewData::class)->weeklySchedule($now);

        $this->assertSame(['Individual session', 'Group session'], $result->pluck('type')->all());
        $this->assertSame(['Teacher', 'Teacher'], $result->pluck('teacher')->all());
        $this->assertSame('Test Course · Customer', $result[0]['title']);
    }

    private function insertTimesheet(int $employeeId, CarbonImmutable $now, string $start, string $end): void
    {
        DB::table('employee_timesheets')->insert([
            'employee_id' => $employeeId,
            'date' => $now->toDateString(),
            'time_start' => $now->toDateString().' '.$start,
            'time_end' => $now->toDateString().' '.$end,
            'category' => 'work',
            'number_of_hours' => 2,
            'session_id' => Session::factory()->create()->id,
            'legacy_session_reference' => 'test-session',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function courseProduct(Course $course): Product
    {
        $product = $course->product()->firstOrFail();
        $product->update(['title' => $course->title, 'status' => 'active']);

        return $product;
    }

    private function insertRevenue(int $productId, string $month, int $amount): void
    {
        DB::table('product_revenues')->insert([
            'product_id' => $productId,
            'customer_invoice_id' => 1,
            'month' => $month,
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCost(int $productId, string $month, int $amount): void
    {
        DB::table('product_costs')->insert([
            'product_id' => $productId,
            'product_resource_id' => 1,
            'month' => $month,
            'allocation_percent' => 100,
            'resource_amount' => $amount,
            'allocated_amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
