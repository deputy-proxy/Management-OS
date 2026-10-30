<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractPayrollInput;
use App\Models\EmployeePaySlip;
use App\Models\EmployeePaySlipItem;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollPeriodLifecycleEvent;
use App\Models\PayrollPolicy;
use App\Models\Session;
use App\Models\User;
use App\Services\EmployeePaySlipService;
use App\Services\Payroll\PayrollCalculationRunService;
use App\Services\Payroll\PayrollPeriodLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class PayrollPeriodLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'payroll_calculation_run.create', 'payroll_period.review', 'payroll_period.approve',
            'payroll_period.close', 'payroll_period.correct',
        ] as $ability) {
            Gate::define($ability, static fn (): bool => true);
        }
    }

    public function test_review_approval_and_close_use_distinct_actors_and_freeze_a_versioned_snapshot(): void
    {
        [$initiator, $reviewer, $approver, $closer, $contract, $input, $policy, $paySlip] = $this->fixture();
        $run = $this->calculate($initiator, $contract, $input, $policy, $paySlip);
        $service = app(PayrollPeriodLifecycleService::class);

        $reviewed = $service->submitForReview($reviewer, $paySlip, 'Synthetic period reviewed against its source records.');
        $this->assertSame('in_review', $reviewed->approval_status);
        $this->assertSame($reviewer->id, $reviewed->reviewed_by);
        $this->assertSame(1, $reviewed->lifecycle_version);
        $this->assertSame(64, strlen($reviewed->review_snapshot_hash));
        $this->assertSame($run->calculation_version, $reviewed->review_snapshot['calculation_runs'][0]['calculation_version']);
        $this->assertSame($initiator->id, $reviewed->review_snapshot['calculation_runs'][0]['initiated_by']);
        $this->assertSame(1, $reviewed->review_snapshot['calculation_runs'][0]['policy_snapshot']['version']);
        $this->assertSame(1, $reviewed->lifecycleEvents->count());
        try {
            $this->calculate($initiator, $contract, $input, $policy, $reviewed);
            $this->fail('A calculation cannot be appended after the period enters review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_pay_slip_id', $exception->errors());
        }

        try {
            EmployeePaySlipItem::query()->create(['employee_pay_slip_id' => $reviewed->id]);
            $this->fail('Source items cannot be added after a period enters review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_pay_slip_id', $exception->errors());
        }

        try {
            $reviewed->update(['manual_amount' => '99.00']);
            $this->fail('A period in review must not be editable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $approved = $service->approve($approver, $reviewed, 'Synthetic approval after independent review.');
        $this->assertSame('approved', $approved->approval_status);
        $this->assertSame($approver->id, $approved->approved_by);
        $this->assertNotSame($approved->reviewed_by, $approved->approved_by);
        $this->assertSame($approved->review_snapshot_hash, $approved->approved_snapshot_hash);
        $this->assertSame($approved->review_snapshot, $approved->approved_snapshot);
        $this->assertCount(2, $approved->lifecycleEvents);

        $closed = $service->close($closer, $approved, 'Synthetic period close verified.');
        $this->assertSame('Closed', $closed->status);
        $this->assertSame('closed', $closed->approval_status);
        $this->assertSame($closer->id, $closed->closed_by);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame(3, $closed->lifecycle_version);
        $this->assertCount(3, $closed->lifecycleEvents);
        $this->assertSame(['submitted_for_review', 'approved', 'closed'], $closed->lifecycleEvents->pluck('event_type')->all());
        $this->assertDatabaseHas('payroll_period_lifecycle_events', [
            'employee_pay_slip_id' => $paySlip->id,
            'actor_id' => $closer->id,
            'event_type' => 'closed',
            'snapshot_hash' => $approved->approved_snapshot_hash,
        ]);
        $closeEvent = $closed->lifecycleEvents->last();
        try {
            $closeEvent->update(['note' => 'Tampered audit event.']);
            $this->fail('Lifecycle events must be append-only.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        try {
            $closeEvent->delete();
            $this->fail('Lifecycle events must not be deletable.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }
        try {
            $closed->forceFill(['approved_snapshot' => ['tampered' => true]])->save();
            $this->fail('Approved snapshots must be immutable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_status', $exception->errors());
        }
        $closed->refresh();

        try {
            $closed->update(['manual_amount' => '100.00']);
            $this->fail('A closed period must preserve its approved financial snapshot.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        try {
            $closed->update(['status' => 'Paid', 'paid_on' => '2026-02-28']);
            $this->fail('A period must not be marked Paid without verified payment evidence.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame($approved->approved_snapshot_hash, $closed->fresh()->approved_snapshot_hash);
    }

    public function test_duplicate_transitions_and_self_approval_are_rejected_under_locked_state_checks(): void
    {
        [$initiator, $reviewer, $approver, $closer, $contract, $input, $policy, $paySlip] = $this->fixture();
        $this->calculate($initiator, $contract, $input, $policy, $paySlip);
        $service = app(PayrollPeriodLifecycleService::class);
        try {
            $service->submitForReview($initiator, $paySlip);
            $this->fail('The calculation initiator must not review their own run.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reviewed_by', $exception->errors());
        }
        $reviewed = $service->submitForReview($reviewer, $paySlip);

        try {
            $service->submitForReview($reviewer, $paySlip);
            $this->fail('A stale second submission must not duplicate the transition.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_status', $exception->errors());
        }
        try {
            $service->approve($reviewer, $reviewed, 'Self approval is not allowed.');
            $this->fail('The reviewer must not approve their own review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approved_by', $exception->errors());
        }

        $approved = $service->approve($approver, $reviewed, 'Independent approval.');
        try {
            $service->approve($approver, $approved, 'Duplicate approval.');
            $this->fail('A second approval must not be recorded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_status', $exception->errors());
        }

        $closed = $service->close($closer, $approved, 'Close the approved period.');
        try {
            $service->close($closer, $approved, 'Duplicate close from a stale model.');
            $this->fail('A stale concurrent close must not be recorded twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_status', $exception->errors());
        }
        $this->assertSame('Closed', $closed->status);
    }

    public function test_snapshot_drift_after_review_blocks_approval(): void
    {
        [$initiator, $reviewer, $approver, , $contract, $input, $policy, $paySlip] = $this->fixture();
        $this->calculate($initiator, $contract, $input, $policy, $paySlip);
        $service = app(PayrollPeriodLifecycleService::class);
        $reviewed = $service->submitForReview($reviewer, $paySlip);

        DB::table('employee_pay_slips')->where('id', $paySlip->id)->update(['manual_amount' => '10.00', 'amount' => '10.00']);

        $this->expectException(ValidationException::class);
        $service->approve($approver, $reviewed, 'Snapshot must match review.');
    }

    public function test_open_period_correction_stays_on_the_same_draft_payslip(): void
    {
        [, , , $actor, $contract, , , $paySlip] = $this->fixture();
        $original = app(EmployeePaySlipService::class)->addSession(Session::factory()->individual()->create([
            'teacher_id' => $contract->employee_id,
            'starts_at' => '2026-01-13 09:00:00',
            'ends_at' => '2026-01-13 10:00:00',
            'status' => 'completed',
        ]))['item'];

        $result = app(PayrollPeriodLifecycleService::class)->recordCorrection($actor, $paySlip, $original, -5.50, 'Correct an open-period source line.');

        $this->assertTrue($result['created']);
        $this->assertSame($paySlip->id, $result['item']->employee_pay_slip_id);
        $this->assertSame('draft', $paySlip->fresh()->approval_status);
        $this->assertSame('20.00', $original->fresh()->amount);
        $this->assertDatabaseHas('payroll_period_lifecycle_events', [
            'employee_pay_slip_id' => $paySlip->id,
            'related_pay_slip_id' => $paySlip->id,
            'related_item_id' => $result['item']->id,
            'event_type' => 'correction_recorded',
            'actor_id' => $actor->id,
        ]);
    }

    public function test_correction_is_audited_on_a_later_open_period_without_changing_the_closed_snapshot(): void
    {
        [$initiator, $reviewer, $approver, $closer, $contract, $input, $policy, $paySlip] = $this->fixture();
        $originalItem = app(EmployeePaySlipService::class)->addSession(Session::factory()->individual()->create([
            'teacher_id' => $contract->employee_id,
            'starts_at' => '2026-01-12 09:00:00',
            'ends_at' => '2026-01-12 10:00:00',
            'status' => 'completed',
        ]))['item'];
        $this->calculate($initiator, $contract, $input, $policy, $paySlip);
        $lifecycle = app(PayrollPeriodLifecycleService::class);
        $lifecycle->submitForReview($reviewer, $paySlip);
        $approved = $lifecycle->approve($approver, $paySlip->fresh(), 'Approved synthetic source snapshot.');
        $closed = $lifecycle->close($closer, $approved, 'Closed synthetic period.');
        $originalSnapshot = $closed->approved_snapshot;
        $originalHash = $closed->approved_snapshot_hash;

        $nextPeriod = EmployeePaySlip::factory()->create([
            'employee_id' => $contract->employee_id,
            'month' => '2026-02-01',
        ]);
        Gate::define('payroll_period.correct', static fn (): bool => false);
        try {
            $lifecycle->recordCorrection($closer, $nextPeriod, $originalItem, -5.50, 'Unauthorized correction.');
            $this->fail('Corrections must require service-layer authorization.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $nextPeriod->fresh()->lineItems()->count());
        }
        Gate::define('payroll_period.correct', static fn (): bool => true);
        $result = $lifecycle->recordCorrection($closer, $nextPeriod, $originalItem, -5.50, 'Correct an overpaid source item.');

        $this->assertTrue($result['created']);
        $this->assertSame('-5.50', $result['item']->amount);
        $this->assertSame('20.00', $originalItem->fresh()->amount);
        $this->assertSame($originalSnapshot, $closed->fresh()->approved_snapshot);
        $this->assertSame($originalHash, $closed->fresh()->approved_snapshot_hash);
        $this->assertDatabaseHas('payroll_period_lifecycle_events', [
            'employee_pay_slip_id' => $closed->id,
            'related_pay_slip_id' => $nextPeriod->id,
            'related_item_id' => $result['item']->id,
            'event_type' => 'correction_recorded',
            'actor_id' => $closer->id,
        ]);

        $eventsAfterCorrection = PayrollPeriodLifecycleEvent::query()->count();
        $duplicate = $lifecycle->recordCorrection($closer, $nextPeriod, $originalItem, -5.50, 'Duplicate correction attempt.');
        $this->assertFalse($duplicate['created']);
        $this->assertSame($eventsAfterCorrection, PayrollPeriodLifecycleEvent::query()->count());

        $marchPeriod = EmployeePaySlip::factory()->create([
            'employee_id' => $contract->employee_id,
            'month' => '2026-03-01',
        ]);
        $reversal = $lifecycle->reverse($closer, $marchPeriod, $originalItem, 'Reverse the original session item.');
        $this->assertTrue($reversal['created']);
        $this->assertSame('-20.00', $reversal['item']->amount);
        $this->assertDatabaseHas('payroll_period_lifecycle_events', [
            'employee_pay_slip_id' => $closed->id,
            'related_pay_slip_id' => $marchPeriod->id,
            'related_item_id' => $reversal['item']->id,
            'event_type' => 'reversal_recorded',
            'actor_id' => $closer->id,
        ]);
        $this->assertSame($originalSnapshot, $closed->fresh()->approved_snapshot);
    }

    public function test_transition_and_correction_transactions_roll_back_all_events_and_financial_items(): void
    {
        [$initiator, $reviewer, $approver, $closer, $contract, $input, $policy, $paySlip] = $this->fixture();
        $this->calculate($initiator, $contract, $input, $policy, $paySlip);
        $lifecycle = app(PayrollPeriodLifecycleService::class);
        try {
            DB::transaction(function () use ($lifecycle, $reviewer, $paySlip): void {
                $lifecycle->submitForReview($reviewer, $paySlip);
                throw new RuntimeException('Synthetic rollback.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic rollback.', $exception->getMessage());
        }
        $this->assertSame('draft', $paySlip->fresh()->approval_status);
        $this->assertDatabaseMissing('payroll_period_lifecycle_events', ['employee_pay_slip_id' => $paySlip->id]);

        $originalItem = app(EmployeePaySlipService::class)->addSession(Session::factory()->individual()->create([
            'teacher_id' => $contract->employee_id,
            'starts_at' => '2026-01-15 09:00:00',
            'ends_at' => '2026-01-15 10:00:00',
            'status' => 'completed',
        ]))['item'];
        $lifecycle->submitForReview($reviewer, $paySlip);
        $approved = $lifecycle->approve($approver, $paySlip->fresh(), 'Approval for rollback fixture.');
        $closed = $lifecycle->close($closer, $approved, 'Close for rollback fixture.');
        $target = EmployeePaySlip::factory()->create(['employee_id' => $contract->employee_id, 'month' => '2026-02-01']);
        $eventCount = PayrollPeriodLifecycleEvent::query()->count();
        try {
            DB::transaction(function () use ($lifecycle, $closer, $target, $originalItem): void {
                $lifecycle->recordCorrection($closer, $target, $originalItem, 12.00, 'Synthetic rollback correction.');
                throw new RuntimeException('Correction rollback.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Correction rollback.', $exception->getMessage());
        }
        $this->assertSame($eventCount, PayrollPeriodLifecycleEvent::query()->count());
        $this->assertSame(0, $target->fresh()->lineItems()->count());
        $this->assertSame('Closed', $closed->fresh()->status);
    }

    public function test_permissions_are_enforced_in_the_service_and_lifecycle_migration_preserves_audit_history(): void
    {
        [$initiator, $reviewer, $approver, $closer, $contract, $input, $policy, $paySlip] = $this->fixture();
        $this->calculate($initiator, $contract, $input, $policy, $paySlip);
        $lifecycle = app(PayrollPeriodLifecycleService::class);
        Gate::define('payroll_period.review', static fn (): bool => false);

        try {
            $lifecycle->submitForReview($reviewer, $paySlip);
            $this->fail('Submitting for review must require service-layer authorization.');
        } catch (AuthorizationException) {
            $this->assertSame('draft', $paySlip->fresh()->approval_status);
        }

        Gate::define('payroll_period.review', static fn (): bool => true);
        $reviewed = $lifecycle->submitForReview($reviewer, $paySlip);
        Gate::define('payroll_period.approve', static fn (): bool => false);
        try {
            $lifecycle->approve($approver, $reviewed, 'Unauthorized approval.');
            $this->fail('Approval must require service-layer authorization.');
        } catch (AuthorizationException) {
            $this->assertSame('in_review', $reviewed->fresh()->approval_status);
        }

        Gate::define('payroll_period.approve', static fn (): bool => true);
        $approved = $lifecycle->approve($approver, $reviewed, 'Authorized synthetic approval.');
        Gate::define('payroll_period.close', static fn (): bool => false);
        try {
            $lifecycle->close($closer, $approved, 'Unauthorized close.');
            $this->fail('Close must require service-layer authorization.');
        } catch (AuthorizationException) {
            $this->assertSame('approved', $approved->fresh()->approval_status);
        }

        Gate::define('payroll_period.close', static fn (): bool => true);
        $lifecycle->close($closer, $approved, 'Authorized synthetic close.');
        $migration = require base_path('database/migrations/2026_10_30_000003_add_payroll_period_lifecycle.php');
        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    /** @return array{User, User, User, User, EmployeeContract, EmployeeContractPayrollInput, PayrollPolicy, EmployeePaySlip} */
    private function fixture(): array
    {
        $initiator = User::factory()->create();
        $reviewer = User::factory()->create();
        $approver = User::factory()->create();
        $closer = User::factory()->create();
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
            'reviewed_by' => $initiator->id,
            'reviewed_at' => now(),
        ]);
        $parameters = [
            'base_pay' => ['type' => 'input_amount', 'input_field' => 'base_amount', 'expected_salary_basis' => 'monthly', 'item_type' => 'earning', 'label' => 'Synthetic base pay'],
            'transport' => ['type' => 'input_allowance', 'allowance_code' => 'synthetic_allowance', 'amount_basis' => 'monthly_amount', 'item_type' => 'earning', 'label' => 'Synthetic allowance'],
            'bonus' => ['type' => 'percentage_of', 'base_item_code' => 'base_pay', 'rate_percent' => '10', 'item_type' => 'earning', 'label' => 'Synthetic bonus'],
            'deduction' => ['type' => 'percentage_of', 'base_item_code' => 'base_pay', 'rate_percent' => '5', 'item_type' => 'deduction', 'label' => 'Synthetic deduction'],
        ];
        $policy = PayrollPolicy::factory()->create([
            'policy_key' => 'lifecycle_synthetic_policy',
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

        return [$initiator, $reviewer, $approver, $closer, $contract, $input, $policy, $paySlip];
    }

    private function calculate(User $actor, EmployeeContract $contract, EmployeeContractPayrollInput $input, PayrollPolicy $policy, EmployeePaySlip $paySlip): PayrollCalculationRun
    {
        return app(PayrollCalculationRunService::class)->calculate(
            $actor,
            $contract,
            '2026-01-01',
            '2026-01-31',
            'lifecycle-run-'.$paySlip->getKey(),
            $input,
            $policy,
            $paySlip,
        );
    }
}
