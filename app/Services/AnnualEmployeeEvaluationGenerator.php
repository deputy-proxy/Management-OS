<?php

namespace App\Services;

use App\Mail\EmployeeEvaluationInitiationMail;
use App\Models\Employee;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeEvaluationForm;
use App\Models\JobDescription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnnualEmployeeEvaluationGenerator
{
    public function __construct(private readonly EmployeeEvaluationScoringService $scoring) {}

    /**
     * Generate the annual snapshot for the selected evaluation year.
     *
     * @return array{created: int, existing: int, skipped: int, notified: int, errors: array<int, string>}
     */
    public function generate(int $evaluationYear, bool $dryRun = false): array
    {
        $report = ['created' => 0, 'existing' => 0, 'skipped' => 0, 'notified' => 0, 'errors' => []];
        $dueDate = Carbon::create($evaluationYear + 1, 3, 31)->toDateString();

        Employee::query()
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($evaluationYear, $dueDate, $dryRun, &$report): void {
                foreach ($employees as $employee) {
                    $context = $this->resolve($employee);

                    if ($context['error'] !== null) {
                        $report['skipped']++;
                        $report['errors'][] = sprintf('Employee #%d (%s): %s', $employee->id, $employee->full_name, $context['error']);

                        continue;
                    }

                    $existing = EmployeeEvaluation::withTrashed()
                        ->where('employee_id', $employee->id)
                        ->where('evaluation_year', $evaluationYear)
                        ->first();

                    if ($existing) {
                        $report['existing']++;
                        if (! $dryRun && $existing->trashed() === false && $existing->initiation_email_sent_at === null) {
                            try {
                                $this->sendInitiationEmail($existing, $context['employee_email'], $context['evaluator_email']);
                                $report['notified']++;
                            } catch (Throwable $exception) {
                                $report['errors'][] = sprintf('Employee #%d: initiation email failed: %s', $employee->id, $exception->getMessage());
                            }
                        }

                        continue;
                    }

                    if ($dryRun) {
                        $report['created']++;

                        continue;
                    }

                    try {
                        $evaluation = DB::transaction(function () use ($employee, $context, $evaluationYear, $dueDate): EmployeeEvaluation {
                            $existing = EmployeeEvaluation::withTrashed()
                                ->where('employee_id', $employee->id)
                                ->where('evaluation_year', $evaluationYear)
                                ->lockForUpdate()
                                ->first();

                            if ($existing) {
                                return $existing;
                            }

                            return EmployeeEvaluation::query()->create([
                                'employee_id' => $employee->id,
                                'evaluation_year' => $evaluationYear,
                                'criteria' => $context['criteria'],
                                'due_by' => $dueDate,
                                'performed_by' => $context['evaluator_user_id'],
                                'employee_evaluation_form_id' => $context['form_id'],
                                'status' => 'Draft',
                            ]);
                        });

                        if ($evaluation->wasRecentlyCreated) {
                            $report['created']++;
                        } else {
                            $report['existing']++;
                        }

                        if (! $evaluation->trashed() && $evaluation->initiation_email_sent_at === null) {
                            $this->sendInitiationEmail($evaluation, $context['employee_email'], $context['evaluator_email']);
                            $report['notified']++;
                        }
                    } catch (Throwable $exception) {
                        $report['errors'][] = sprintf('Employee #%d: %s', $employee->id, $exception->getMessage());
                    }
                }
            });

        return $report;
    }

    /**
     * @return array{error: ?string, form_id?: int, criteria?: array<int, array<string, mixed>>, evaluator_user_id?: int, employee_email?: string, evaluator_email?: string}
     */
    private function resolve(Employee $employee): array
    {
        $jobDescriptionId = $employee->getAttribute('job_description');

        if (! is_numeric($jobDescriptionId)) {
            return ['error' => 'missing job description'];
        }

        $jobDescription = JobDescription::query()->find((int) $jobDescriptionId);

        if (! $jobDescription) {
            return ['error' => 'missing job description'];
        }

        $lineManagerId = $jobDescription->getAttribute('line_manager_id');

        if (! is_numeric($lineManagerId)) {
            return ['error' => 'missing line-manager job description'];
        }

        $lineManager = JobDescription::query()->find((int) $lineManagerId);

        if (! $lineManager) {
            return ['error' => 'missing line-manager job description'];
        }

        $managerEmployees = Employee::query()
            ->where('job_description', $lineManager->id)
            ->get();

        if ($managerEmployees->count() !== 1) {
            return ['error' => sprintf('line-manager job description #%d must map to exactly one non-deleted employee; found %d', $lineManager->id, $managerEmployees->count())];
        }

        $evaluator = $managerEmployees->first();

        if (! $evaluator instanceof Employee) {
            return ['error' => 'the line manager employee record could not be resolved'];
        }

        $employeeUser = User::query()->find($employee->getAttribute('user_id'));
        $evaluatorUser = User::query()->find($evaluator->getAttribute('user_id'));
        $employeeEmail = $employeeUser?->email;
        $evaluatorEmail = $evaluatorUser?->email;

        if (! is_string($employeeEmail) || ! filter_var($employeeEmail, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'employee has no valid email address on the linked user'];
        }

        if (! is_string($evaluatorEmail) || ! filter_var($evaluatorEmail, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'line manager has no valid email address on the linked user'];
        }

        $formsQuery = EmployeeEvaluationForm::query()->where('job_description_id', $jobDescription->id);
        $formCount = (clone $formsQuery)->count();

        if ($formCount !== 1) {
            return ['error' => sprintf('job description #%d must have exactly one active evaluation form; found %d', $jobDescription->id, $formCount)];
        }

        $form = $formsQuery->first();

        if (! $form instanceof EmployeeEvaluationForm) {
            return ['error' => 'the evaluation form could not be resolved'];
        }

        /** @var mixed $templateCriteria */
        $templateCriteria = $form->getAttribute('criteria');

        if (! is_array($templateCriteria) || $templateCriteria === []) {
            return ['error' => 'the evaluation form has no criteria'];
        }

        try {
            $criteria = $this->scoring->snapshotTemplate($templateCriteria);
        } catch (ValidationException $exception) {
            return ['error' => 'invalid evaluation form: '.collect($exception->errors())->flatten()->implode(' ')];
        }

        return [
            'error' => null,
            'form_id' => $form->id,
            'criteria' => $criteria,
            'evaluator_user_id' => $evaluator->getAttribute('user_id'),
            'employee_email' => $employeeEmail,
            'evaluator_email' => $evaluatorEmail,
        ];
    }

    private function sendInitiationEmail(EmployeeEvaluation $evaluation, string $employeeEmail, string $evaluatorEmail): void
    {
        $mail = Mail::to($employeeEmail);

        if (strtolower($employeeEmail) !== strtolower($evaluatorEmail)) {
            $mail->cc($evaluatorEmail);
        }

        $mail->send(new EmployeeEvaluationInitiationMail($evaluation->fresh(['employee.user', 'performedBy'])));

        $evaluation->forceFill(['initiation_email_sent_at' => now()])->save();
    }
}
