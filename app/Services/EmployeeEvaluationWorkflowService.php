<?php

namespace App\Services;

use App\Mail\EmployeeEvaluationSubmittedMail;
use App\Models\Employee;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeEvaluationForm;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EmployeeEvaluationWorkflowService
{
    public function __construct(private readonly EmployeeEvaluationScoringService $scoring) {}

    public function start(EmployeeEvaluation $evaluation): void
    {
        DB::transaction(function () use ($evaluation): void {
            $record = EmployeeEvaluation::query()->lockForUpdate()->findOrFail($evaluation->id);

            if ($record->status !== 'Draft') {
                throw ValidationException::withMessages(['status' => 'Only Draft evaluations can be marked In Progress.']);
            }

            $record->transitionTo('In Progress');
        });
    }

    public function submit(EmployeeEvaluation $evaluation): void
    {
        $token = Str::random(64);

        $evaluation = DB::transaction(function () use ($evaluation, $token): EmployeeEvaluation {
            $record = EmployeeEvaluation::query()->lockForUpdate()->findOrFail($evaluation->id);

            if (! in_array($record->status, ['Draft', 'In Progress'], true)) {
                throw ValidationException::withMessages(['status' => 'Only Draft or In Progress evaluations can be submitted.']);
            }

            $employee = Employee::query()->find($record->getAttribute('employee_id'));
            $form = EmployeeEvaluationForm::query()->find($record->getAttribute('employee_evaluation_form_id'));

            if (! $employee || ! $form || (int) $employee->getAttribute('job_description') !== (int) $form->getAttribute('job_description_id')) {
                throw ValidationException::withMessages(['employee_evaluation_form_id' => "The selected evaluation form must match the employee's job description."]);
            }

            $score = $this->scoring->calculate($record->criteria ?? []);
            $now = now();

            $record->transitionTo('Submitted', [
                'final_score' => $score,
                'submitted_at' => $now,
                'performed_on' => $now->toDateString(),
                'approved_at' => null,
                'acknowledged_at' => null,
                'approval_source' => null,
                'approval_comments' => null,
                'approval_token_hash' => hash('sha256', $token),
                'approval_token_encrypted' => $token,
                'approval_deadline_at' => $now->copy()->addDays(7),
                'approval_token_expires_at' => $now->copy()->addDays(7),
                'submission_email_sent_at' => null,
            ]);

            return $record;
        });

        $this->deliverApprovalEmail($evaluation);
    }

    public function resendApprovalLink(EmployeeEvaluation $evaluation): void
    {
        $token = Str::random(64);

        DB::transaction(function () use ($evaluation, $token): void {
            $record = EmployeeEvaluation::query()->lockForUpdate()->findOrFail($evaluation->id);

            if ($record->status !== 'Submitted' || $record->acknowledged_at !== null) {
                throw ValidationException::withMessages(['status' => 'An approval link can only be issued for a pending submitted evaluation.']);
            }

            $record->forceFill([
                'approval_token_hash' => hash('sha256', $token),
                'approval_token_encrypted' => $token,
                'approval_deadline_at' => now()->addDays(7),
                'approval_token_expires_at' => now()->addDays(7),
                'submission_email_sent_at' => null,
            ]);
        });

        $this->deliverApprovalEmail($evaluation->fresh());
    }

    private function deliverApprovalEmail(EmployeeEvaluation $evaluation): void
    {
        $employee = Employee::query()->find($evaluation->getAttribute('employee_id'));
        $user = $employee ? User::query()->find($employee->getAttribute('user_id')) : null;
        $email = $user?->email;

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The evaluated employee has no valid email address.');
        }

        $token = $evaluation->approval_token_encrypted;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('The approval token is unavailable. Issue a new approval link.');
        }

        Mail::to($email)->send(new EmployeeEvaluationSubmittedMail($evaluation->fresh(['employee.user', 'performedBy', 'employeeEvaluationForm.jobDescription']), $token));

        $sentAt = now();
        $deadline = $sentAt->copy()->addDays(7);

        $evaluation->forceFill([
            'submission_email_sent_at' => $sentAt,
            'approval_deadline_at' => $deadline,
            'approval_token_expires_at' => $deadline,
        ])->save();
    }

    public function acknowledge(int $evaluationId, string $token, ?string $comments): EmployeeEvaluation
    {
        return DB::transaction(function () use ($evaluationId, $token, $comments): EmployeeEvaluation {
            $evaluation = EmployeeEvaluation::query()->lockForUpdate()->findOrFail($evaluationId);
            $deadline = $evaluation->getAttribute('approval_deadline_at');
            $tokenExpiresAt = $evaluation->getAttribute('approval_token_expires_at');

            if (
                $evaluation->status !== 'Submitted'
                || $evaluation->submission_email_sent_at === null
                || $deadline === null
                || ! Carbon::parse($deadline)->isFuture()
                || $tokenExpiresAt === null
                || ! Carbon::parse($tokenExpiresAt)->isFuture()
                || ! is_string($evaluation->approval_token_hash)
                || ! hash_equals($evaluation->approval_token_hash, hash('sha256', $token))
            ) {
                throw ValidationException::withMessages(['token' => 'This acknowledgment link is invalid, expired, or has already been used.']);
            }

            $evaluation->transitionTo('Approved', [
                'approved_at' => now(),
                'acknowledged_at' => now(),
                'approval_source' => 'employee_acknowledged',
                'approval_comments' => $comments,
                'approval_token_hash' => null,
                'approval_token_encrypted' => null,
                'approval_token_expires_at' => null,
            ]);

            return $evaluation;
        });
    }
}
