<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractPayrollInput;
use App\Models\EmployeePaySlip;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollException;
use App\Models\PayrollExportEvent;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationRunService;
use App\Services\Payroll\PayrollPeriodExportService;
use App\Services\Payroll\PayrollPeriodLifecycleService;
use App\Services\Payroll\PayrollValidationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PayrollPeriodExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'payroll_calculation_run.create', 'payroll_period.review', 'payroll_period.approve',
            'payroll_period.close', 'payroll_period.export', 'payroll_exception.resolve',
        ] as $ability) {
            Gate::define($ability, static fn (): bool => true);
        }
    }

    public function test_internal_summary_line_items_and_exception_exports_are_deterministic_and_audited(): void
    {
        [$actor, $reviewer, $approver, $contract, $input, $policy, $paySlip, $run] = $this->fixture();
        $exception = PayrollException::query()->create([
            'payroll_calculation_run_id' => $run->id,
            'employee_pay_slip_id' => $paySlip->id,
            'employee_contract_id' => $contract->id,
            'employee_id' => $contract->employee_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'exception_key' => hash('sha256', 'export-test-warning-'.$run->id),
            'code' => 'synthetic_export_warning',
            'severity' => PayrollException::SEVERITY_WARNING,
            'status' => PayrollException::STATUS_OPEN,
            'message' => 'Avertisment pentru verificarea exportului',
            'resolution_guidance' => 'Verificați sursa înainte de raportarea internă',
            'affected_records' => ['calculation_run_id' => $run->id],
            'detected_by' => $actor->id,
            'detected_at' => now(),
        ]);
        $lifecycle = app(PayrollPeriodLifecycleService::class);
        $lifecycle->submitForReview($reviewer, $paySlip);
        $lifecycle->approve($approver, $paySlip->fresh(), 'Approve export fixture.');

        $service = app(PayrollPeriodExportService::class);
        $summary = $service->export($actor, $paySlip, $run->id, 'summary', 'csv');
        $summaryAgain = $service->export($actor, $paySlip, $run->id, 'summary', 'csv');
        $this->assertSame($summary['content'], $summaryAgain['content']);
        $this->assertSame($summary['content_sha256'], $summaryAgain['content_sha256']);
        $this->assertSame(hash('sha256', $summary['content']), $summary['content_sha256']);
        $this->assertSame(1, $summary['row_count']);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $summary['content']);
        $this->assertStringContainsString('internal_payroll_summary', $summary['content']);
        $this->assertStringContainsString('RON', $summary['content']);

        $lineItems = $service->export($actor, $paySlip, $run->id, 'line_items', 'csv');
        $this->assertSame(4, $lineItems['row_count']);
        $this->assertStringContainsString('Bonus de performanță', $lineItems['content']);
        $this->assertStringContainsString('Indemnizație transport', $lineItems['content']);
        $this->assertStringContainsString("'=HYPERLINK(", $lineItems['content']);

        $exceptions = $service->export($actor, $paySlip, $run->id, 'exceptions', 'csv');
        $this->assertSame(1, $exceptions['row_count']);
        $this->assertStringContainsString('Avertisment pentru verificarea exportului', $exceptions['content']);
        $this->assertStringNotContainsString('resolution_note', $exceptions['content']);

        app(PayrollValidationService::class)->resolve($actor, $exception, 'Resolved after approval for the export regression test.');
        $exceptionsAfterResolution = $service->export($actor, $paySlip, $run->id, 'exceptions', 'csv');
        $this->assertSame($exceptions['content'], $exceptionsAfterResolution['content']);
        $this->assertSame($exceptions['content_sha256'], $exceptionsAfterResolution['content_sha256']);

        $json = $service->export($actor, $paySlip, $run->id, 'summary', 'json');
        $this->assertSame('application/json; charset=UTF-8', $json['media_type']);
        $this->assertStringContainsString('"report_type": "internal_payroll_summary"', $json['content']);
        $this->assertStringContainsString('"gross_amount": "4500.00"', $json['content']);

        $events = PayrollExportEvent::query()->orderBy('id')->get();
        $this->assertCount(6, $events);
        $this->assertSame($actor->id, $events->first()->actor_id);
        $this->assertSame($paySlip->id, $events->first()->employee_pay_slip_id);
        $this->assertSame($run->id, $events->first()->payroll_calculation_run_id);
        $this->assertSame($paySlip->fresh()->approved_snapshot_hash, $events->first()->approved_snapshot_hash);
        $this->assertSame($summary['content_sha256'], $events->first()->content_sha256);
        $this->assertSame(1, $events->first()->row_count);

        try {
            $events->first()->update(['content_sha256' => str_repeat('0', 64)]);
            $this->fail('Export audit records must be immutable.');
        } catch (\LogicException $exceptionError) {
            $this->assertStringContainsString('immutable', $exceptionError->getMessage());
        }
        try {
            $events->first()->delete();
            $this->fail('Export audit records cannot be deleted.');
        } catch (\LogicException $exceptionError) {
            $this->assertStringContainsString('cannot be deleted', $exceptionError->getMessage());
        }
    }

    public function test_json_and_csv_encoding_errors_and_unsupported_report_formats_are_rejected(): void
    {
        [$actor, $reviewer, $approver, $contract, , , $paySlip, $run] = $this->fixture();
        $service = app(PayrollPeriodExportService::class);

        try {
            $service->export($actor, $paySlip, $run->id, 'statutory_submission', 'csv');
            $this->fail('Statutory submissions must not be represented as supported internal exports.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('statutory submissions are not available', collect($exception->errors())->flatten()->first());
        }
        try {
            $service->export($actor, $paySlip, $run->id, 'summary', 'xlsx');
            $this->fail('Unsupported export formats must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('format', $exception->errors());
        }

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite is used to insert an intentionally invalid UTF-8 byte for serializer validation.');
        }

        PayrollException::query()->create([
            'payroll_calculation_run_id' => $run->id,
            'employee_pay_slip_id' => $paySlip->id,
            'employee_contract_id' => $contract->id,
            'employee_id' => $contract->employee_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'exception_key' => hash('sha256', 'export-invalid-utf8-'.$run->id),
            'code' => 'invalid_utf8_fixture',
            'severity' => PayrollException::SEVERITY_WARNING,
            'status' => PayrollException::STATUS_OPEN,
            'message' => "Invalid byte: \xFF",
            'resolution_guidance' => 'Fix the encoding before export.',
            'detected_by' => $actor->id,
            'detected_at' => now(),
        ]);
        $lifecycle = app(PayrollPeriodLifecycleService::class);
        $lifecycle->submitForReview($reviewer, $paySlip);
        $lifecycle->approve($approver, $paySlip->fresh(), 'Approve invalid encoding fixture.');

        $this->expectException(ValidationException::class);
        $service->export($actor, $paySlip, $run->id, 'exceptions', 'csv');
    }

    public function test_missing_required_policy_fields_permissions_and_period_lock_are_enforced(): void
    {
        [$actor, $reviewer, $approver, , , , $draftPaySlip, $draftRun] = $this->fixture();
        $service = app(PayrollPeriodExportService::class);

        try {
            $service->export($actor, $draftPaySlip, $draftRun->id, 'summary', 'csv');
            $this->fail('Draft periods must not be exportable as approved payroll reports.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_pay_slip_id', $exception->errors());
        }

        Gate::define('payroll_period.export', static fn (): bool => false);
        try {
            $service->export($actor, $draftPaySlip, $draftRun->id, 'summary', 'csv');
            $this->fail('Payroll exports must require the export permission in the service layer.');
        } catch (AuthorizationException) {
            $this->assertSame(0, PayrollExportEvent::query()->count());
        }

        Gate::define('payroll_period.export', static fn (): bool => true);
        app(PayrollPeriodLifecycleService::class)->submitForReview($reviewer, $draftPaySlip);
        try {
            $service->export($actor, $draftPaySlip->fresh(), $draftRun->id, 'summary', 'csv');
            $this->fail('Periods in review must not be exported as approved reports.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_pay_slip_id', $exception->errors());
        }
        app(PayrollPeriodLifecycleService::class)->approve($approver, $draftPaySlip->fresh(), 'Approve for missing-field export test.');

        $snapshot = $draftPaySlip->fresh()->approved_snapshot;
        $snapshot['calculation_runs'][0]['policy_snapshot']['version'] = null;
        DB::table('employee_pay_slips')->where('id', $draftPaySlip->id)->update(['approved_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        try {
            $service->export($actor, $draftPaySlip->fresh(), $draftRun->id, 'summary', 'csv');
            $this->fail('An export must not proceed without a versioned policy reference.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('calculation_result', $exception->errors());
        }
    }

    public function test_authenticated_download_route_streams_the_selected_export(): void
    {
        [$actor, , , , , , $paySlip, $run] = $this->approvedFixture();
        $url = route('payroll.period.exports.download', [
            'paySlip' => $paySlip->id,
            'type' => 'summary',
            'format' => 'csv',
            'run' => $run->id,
        ]);

        $response = $this->actingAs($actor)->get($url);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('internal_payroll_summary', $response->streamedContent());
    }

    public function test_export_audit_migration_refuses_to_drop_existing_records(): void
    {
        [$actor, , , , , , $paySlip, $run] = $this->approvedFixture();
        app(PayrollPeriodExportService::class)->export($actor, $paySlip, $run->id, 'summary', 'csv');
        $migration = require base_path('database/migrations/2026_10_30_000004_create_payroll_export_events_table.php');

        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    /** @return array{User, User, User, EmployeeContract, EmployeeContractPayrollInput, PayrollPolicy, EmployeePaySlip, PayrollCalculationRun} */
    private function fixture(): array
    {
        $actor = User::factory()->create();
        $reviewer = User::factory()->create();
        $approver = User::factory()->create();
        $employee = Employee::factory()->create();
        $contract = EmployeeContract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'contract_type' => 'full_time',
            'base_salary' => 4000,
            'hourly_rate' => 20,
        ]);
        $input = EmployeeContractPayrollInput::query()->create([
            'employee_contract_id' => $contract->id,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'salary_basis' => 'monthly',
            'base_amount' => '4000.00',
            'currency_code' => 'RON',
            'scheduled_hours_per_week' => 40,
            'work_schedule' => ['pattern' => 'synthetic_weekday'],
            'allowance_inputs' => [['code' => 'synthetic_allowance', 'amount' => '100.00', 'currency_code' => 'RON']],
            'input_status' => 'verified',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
        $parameters = [
            'base_pay' => ['type' => 'input_amount', 'input_field' => 'base_amount', 'expected_salary_basis' => 'monthly', 'item_type' => 'earning', 'label' => '=HYPERLINK("https://example.invalid")'],
            'transport' => ['type' => 'input_allowance', 'allowance_code' => 'synthetic_allowance', 'amount_basis' => 'monthly_amount', 'item_type' => 'earning', 'label' => 'Indemnizație transport'],
            'bonus' => ['type' => 'percentage_of', 'base_item_code' => 'base_pay', 'rate_percent' => '10', 'item_type' => 'earning', 'label' => 'Bonus de performanță'],
            'deduction' => ['type' => 'percentage_of', 'base_item_code' => 'base_pay', 'rate_percent' => '5', 'item_type' => 'deduction', 'label' => 'Reținere'],
        ];
        $policy = PayrollPolicy::factory()->create([
            'policy_key' => 'export_synthetic_policy',
            'version' => 1,
            'status' => 'active',
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'parameters' => $parameters,
            'required_parameters' => array_keys($parameters),
            'calculation_order' => array_keys($parameters),
            'contract_rules' => ['full_time' => ['supported_salary_bases' => ['monthly']]],
            'proration_rule' => null,
            'rounding_mode' => 'half_up',
            'rounding_precision' => 2,
        ]);
        $paySlip = EmployeePaySlip::factory()->create([
            'employee_id' => $employee->id,
            'month' => '2026-01-01',
        ]);
        $run = app(PayrollCalculationRunService::class)->calculate(
            $actor,
            $contract,
            '2026-01-01',
            '2026-01-31',
            'export-synthetic-run-'.$paySlip->id,
            $input,
            $policy,
            $paySlip,
        );

        return [$actor, $reviewer, $approver, $contract, $input, $policy, $paySlip, $run];
    }

    /** @return array{User, User, User, EmployeeContract, EmployeeContractPayrollInput, PayrollPolicy, EmployeePaySlip, PayrollCalculationRun} */
    private function approvedFixture(): array
    {
        [$actor, $reviewer, $approver, $contract, $input, $policy, $paySlip, $run] = $this->fixture();
        app(PayrollPeriodLifecycleService::class)->submitForReview($reviewer, $paySlip);
        app(PayrollPeriodLifecycleService::class)->approve($approver, $paySlip->fresh(), 'Approve the synthetic export period.');

        return [$actor, $reviewer, $approver, $contract, $input, $policy, $paySlip->fresh(), $run];
    }
}
