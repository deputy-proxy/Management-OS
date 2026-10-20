<?php

namespace Tests\Feature\EmployeeEvaluations;

use App\Mail\EmployeeEvaluationInitiationMail;
use App\Mail\EmployeeEvaluationSubmittedMail;
use App\Models\Employee;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeEvaluationForm;
use App\Models\JobDescription;
use App\Models\User;
use App\Services\EmployeeEvaluationScoringService;
use App\Services\EmployeeEvaluationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnnualEmployeeEvaluationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_scores_are_normalized_to_a_percentage_and_yes_no_uses_zero_or_one_hundred(): void
    {
        $score = app(EmployeeEvaluationScoringService::class)->calculate([
            ['type' => 'rated_criterion', 'weight' => 50, 'score' => 1, 'notes' => null],
            ['type' => 'rated_criterion', 'weight' => 25, 'score' => 5, 'notes' => null],
            ['type' => 'yes_no', 'weight' => 25, 'answer' => 'yes', 'notes' => null],
            ['type' => 'text_feedback', 'notes' => 'Useful feedback'],
        ]);

        $this->assertSame(50.0, $score);
    }

    public function test_template_snapshot_preserves_canonical_metadata_and_clears_responses(): void
    {
        $snapshot = app(EmployeeEvaluationScoringService::class)->snapshotTemplate([
            ['type' => 'rated_criterion', 'data' => ['label' => 'Quality', 'description' => 'Quality guidance', 'weight' => 70]],
            ['type' => 'yes_no', 'data' => ['question' => 'Reliable?', 'weight' => 30]],
        ]);

        $this->assertSame('rated_criterion', $snapshot[0]['type']);
        $this->assertSame('Quality', $snapshot[0]['label']);
        $this->assertSame(70, $snapshot[0]['weight']);
        $this->assertNull($snapshot[0]['score']);
        $this->assertNull($snapshot[0]['answer']);
        $this->assertSame('Reliable?', $snapshot[1]['label']);
        $this->assertSame(30, $snapshot[1]['weight']);
    }

    public function test_template_weights_must_total_exactly_one_hundred_percent(): void
    {
        $this->expectException(ValidationException::class);

        app(EmployeeEvaluationScoringService::class)->validateTemplate([
            ['type' => 'rated_criterion', 'data' => ['label' => 'Quality', 'weight' => 60]],
            ['type' => 'yes_no', 'data' => ['question' => 'Reliable?', 'weight' => 39.9999]],
        ]);
    }

    public function test_workflow_only_allows_draft_evaluations_to_start(): void
    {
        $evaluation = $this->createEvaluation([
            ['type' => 'rated_criterion', 'label' => 'Quality', 'weight' => 100, 'score' => null, 'notes' => null],
        ]);
        $workflow = app(EmployeeEvaluationWorkflowService::class);

        $workflow->start($evaluation);
        $this->assertSame('In Progress', $evaluation->fresh()->status);

        try {
            $evaluation->fresh()->update(['status' => 'Approved']);
            $this->fail('Direct status updates must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $workflow->start($evaluation->fresh());
    }

    public function test_invalid_weight_total_prevents_submission(): void
    {
        $evaluation = $this->createEvaluation([
            ['type' => 'rated_criterion', 'label' => 'Quality', 'weight' => 70, 'score' => 5, 'notes' => null],
            ['type' => 'yes_no', 'label' => 'Reliable', 'weight' => 20, 'answer' => 'yes', 'notes' => null],
        ]);

        try {
            app(EmployeeEvaluationWorkflowService::class)->submit($evaluation);
            $this->fail('An evaluation with weights totaling 90% must not be submitted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('criteria', $exception->errors());
        }

        $this->assertSame('Draft', $evaluation->fresh()->status);
        $this->assertNull($evaluation->fresh()->final_score);
    }

    public function test_annual_command_uses_previous_year_is_idempotent_and_emails_employee_and_evaluator_once(): void
    {
        Mail::fake();
        $this->travelTo(now()->setDate(2027, 2, 1)->setTime(1, 0));
        $context = $this->createEmployeeAndManagerContext();

        $this->artisan('evaluations:generate-annual')->assertFailed();
        $this->artisan('evaluations:generate-annual')->assertFailed();

        $evaluation = EmployeeEvaluation::query()->where('employee_id', $context['employee']->id)->firstOrFail();

        $this->assertSame(2026, $evaluation->evaluation_year);
        $this->assertSame('2027-03-31', $evaluation->due_by->toDateString());
        $this->assertSame($context['manager']->user_id, $evaluation->performed_by);
        $this->assertSame('Draft', $evaluation->status);
        $this->assertNotNull($evaluation->initiation_email_sent_at);
        $this->assertSame(1, EmployeeEvaluation::query()->where('employee_id', $context['employee']->id)->where('evaluation_year', 2026)->count());
        Mail::assertSent(EmployeeEvaluationInitiationMail::class);
        Mail::assertSent(EmployeeEvaluationInitiationMail::class, function (EmployeeEvaluationInitiationMail $mail) use ($context): bool {
            return $mail->hasTo($context['employee']->user->email)
                && $mail->hasCc($context['manager']->user->email)
                && str_contains($mail->render(), 'Annual employee evaluation started');
        });
    }

    public function test_one_line_manager_can_be_assigned_to_multiple_employee_evaluations(): void
    {
        Mail::fake();
        $this->travelTo(now()->setDate(2027, 2, 1)->setTime(1, 0));
        $context = $this->createEmployeeAndManagerContext();
        $secondUser = User::factory()->create(['email' => 'second-employee@example.test']);
        $secondEmployee = Employee::factory()->create([
            'job_description' => $context['employeeJob']->id,
            'user_id' => $secondUser->id,
        ]);

        $this->artisan('evaluations:generate-annual')->assertFailed();

        $evaluations = EmployeeEvaluation::query()->whereIn('employee_id', [$context['employee']->id, $secondEmployee->id])->get();
        $this->assertCount(2, $evaluations);
        $this->assertSame([$context['manager']->user_id], $evaluations->pluck('performed_by')->unique()->values()->all());
        $this->assertSame([2026], $evaluations->pluck('evaluation_year')->unique()->values()->all());
        Mail::assertSent(EmployeeEvaluationInitiationMail::class, 2);
    }

    public function test_dry_run_does_not_create_records_or_send_email(): void
    {
        Mail::fake();
        $this->createEmployeeAndManagerContext();

        $this->artisan('evaluations:generate-annual', ['--year' => 2025, '--dry-run' => true])->assertFailed();

        $this->assertSame(0, EmployeeEvaluation::query()->count());
        Mail::assertNothingSent();
    }

    public function test_employee_can_acknowledge_receipt_with_optional_comment_using_single_use_link(): void
    {
        Mail::fake();
        $context = $this->createEmployeeAndManagerContext();
        $evaluation = $this->createEvaluation([
            ['type' => 'rated_criterion', 'label' => 'Quality', 'weight' => 100, 'score' => 4, 'notes' => null],
        ], $context['employee'], $context['manager']);

        app(EmployeeEvaluationWorkflowService::class)->submit($evaluation);
        $submitted = $evaluation->fresh();
        $token = $submitted->approval_token_encrypted;

        $this->assertSame('Submitted', $submitted->status);
        $this->assertSame('75.00', $submitted->final_score);
        $this->assertNotNull($submitted->submission_email_sent_at);
        $this->assertNotNull($submitted->approval_deadline_at);
        Mail::assertSent(EmployeeEvaluationSubmittedMail::class, fn (EmployeeEvaluationSubmittedMail $mail): bool => str_contains($mail->render(), 'You have') && str_contains($mail->render(), '7 days'));

        $showUrl = route('employee-evaluations.acknowledge.show', ['evaluation' => $submitted->id, 'token' => $token]);
        $this->get($showUrl)->assertOk()->assertSee('Acknowledge employee evaluation');

        $storeUrl = route('employee-evaluations.acknowledge.store', ['evaluation' => $submitted->id, 'token' => $token]);
        $this->post($storeUrl, ['comments' => 'I acknowledge receipt.'])->assertOk()->assertSee('Acknowledgment recorded');

        $approved = $submitted->fresh();
        $this->assertSame('Approved', $approved->status);
        $this->assertSame('employee_acknowledged', $approved->approval_source);
        $this->assertNotNull($approved->acknowledged_at);
        $this->assertSame('I acknowledge receipt.', $approved->approval_comments);
        $this->assertNull($approved->approval_token_hash);
        $this->get($showUrl)->assertStatus(410);
    }

    public function test_timeout_processor_auto_approves_after_seven_days_without_falsely_recording_acknowledgment(): void
    {
        Mail::fake();
        $context = $this->createEmployeeAndManagerContext();
        $evaluation = $this->createEvaluation([
            ['type' => 'yes_no', 'label' => 'Reliable', 'weight' => 100, 'answer' => 'no', 'notes' => null],
        ], $context['employee'], $context['manager']);

        app(EmployeeEvaluationWorkflowService::class)->submit($evaluation);
        $token = $evaluation->fresh()->approval_token_encrypted;
        $this->travel(7)->days();
        $expiredUrl = route('employee-evaluations.acknowledge.show', ['evaluation' => $evaluation->id, 'token' => $token]);
        $this->get($expiredUrl)->assertStatus(410);

        $this->artisan('evaluations:process-approval-timeouts')->assertSuccessful();

        $approved = $evaluation->fresh();
        $this->assertSame('Approved', $approved->status);
        $this->assertSame('auto_approved_timeout', $approved->approval_source);
        $this->assertNull($approved->acknowledged_at);
        $this->assertNull($approved->approval_comments);
        $this->assertNotNull($approved->approved_at);
        $this->assertNull($approved->approval_token_hash);
        $this->artisan('evaluations:process-approval-timeouts')->assertSuccessful();
        $this->assertSame('auto_approved_timeout', $evaluation->fresh()->approval_source);
    }

    public function test_job_description_cannot_create_a_circular_line_manager_chain(): void
    {
        $context = $this->createEmployeeAndManagerContext();

        $this->expectException(ValidationException::class);
        $context['managerJob']->update(['line_manager_id' => $context['employeeJob']->id]);
    }

    public function test_generation_reports_ambiguous_manager_mapping_instead_of_guessing(): void
    {
        Mail::fake();
        $context = $this->createEmployeeAndManagerContext();
        Employee::factory()->create([
            'job_description' => $context['managerJob']->id,
            'user_id' => User::factory()->create(['email' => 'second-manager@example.test'])->id,
        ]);

        $this->artisan('evaluations:generate-annual', ['--year' => 2025])
            ->assertFailed()
            ->expectsOutputToContain('must map to exactly one');

        $this->assertDatabaseMissing('employee_evaluations', [
            'employee_id' => $context['employee']->id,
            'evaluation_year' => 2025,
        ]);
    }

    /**
     * @return array{employee: Employee, manager: Employee, managerJob: JobDescription, employeeJob: JobDescription}
     */
    private function createEmployeeAndManagerContext(): array
    {
        $managerJob = JobDescription::factory()->create(['job_title' => 'Line Manager']);
        $employeeJob = JobDescription::factory()->create([
            'job_title' => 'Team Member',
            'line_manager_id' => $managerJob->id,
        ]);

        $managerUser = User::factory()->create(['email' => 'manager@example.test']);
        $employeeUser = User::factory()->create(['email' => 'employee@example.test']);

        $manager = Employee::factory()->create([
            'job_description' => $managerJob->id,
            'user_id' => $managerUser->id,
        ]);
        $employee = Employee::factory()->create([
            'job_description' => $employeeJob->id,
            'user_id' => $employeeUser->id,
        ]);

        EmployeeEvaluationForm::query()->create([
            'job_description_id' => $employeeJob->id,
            'criteria' => [
                ['type' => 'rated_criterion', 'data' => ['label' => 'Quality', 'weight' => 100]],
            ],
        ]);

        return compact('employee', 'manager', 'managerJob', 'employeeJob');
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     */
    private function createEvaluation(array $criteria, ?Employee $employee = null, ?Employee $manager = null): EmployeeEvaluation
    {
        if (! $employee || ! $manager) {
            $context = $this->createEmployeeAndManagerContext();
            $employee = $context['employee'];
            $manager = $context['manager'];
            $form = EmployeeEvaluationForm::query()->where('job_description_id', $employee->job_description)->firstOrFail();
        } else {
            $form = EmployeeEvaluationForm::query()->where('job_description_id', $employee->job_description)->firstOrFail();
        }

        return EmployeeEvaluation::query()->create([
            'employee_id' => $employee->id,
            'criteria' => $criteria,
            'due_by' => now()->addMonths(2)->toDateString(),
            'performed_on' => now()->toDateString(),
            'performed_by' => $manager->user_id,
            'employee_evaluation_form_id' => $form->id,
            'status' => 'Draft',
        ]);
    }
}
