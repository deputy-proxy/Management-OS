<?php

namespace App\Filament\Resources\EmployeeEvaluations\Pages;

use App\Filament\Resources\EmployeeEvaluations\EmployeeEvaluationResource;
use App\Models\Employee;
use App\Models\EmployeeEvaluationForm as EvaluationFormModel;
use App\Services\EmployeeEvaluationScoringService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateEmployeeEvaluation extends CreateRecord
{
    protected static string $resource = EmployeeEvaluationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $employee = Employee::query()->find($data['employee_id'] ?? null);
        $template = EvaluationFormModel::query()->find($data['employee_evaluation_form_id'] ?? null);

        if (! $employee || ! $template || (int) $employee->getAttribute('job_description') !== (int) $template->getAttribute('job_description_id')) {
            throw ValidationException::withMessages(['employee_evaluation_form_id' => "Choose an evaluation form that matches the employee's job description."]);
        }

        $data['criteria'] = app(EmployeeEvaluationScoringService::class)->snapshotTemplate($template->getAttribute('criteria') ?? []);
        unset($data['evaluation_year'], $data['final_score'], $data['submitted_at'], $data['approved_at'], $data['acknowledged_at'], $data['approval_deadline_at'], $data['approval_source'], $data['approval_comments'], $data['approval_token_hash'], $data['approval_token_encrypted'], $data['approval_token_expires_at'], $data['initiation_email_sent_at'], $data['submission_email_sent_at']);
        $data['status'] = 'Draft';

        return $data;
    }
}
