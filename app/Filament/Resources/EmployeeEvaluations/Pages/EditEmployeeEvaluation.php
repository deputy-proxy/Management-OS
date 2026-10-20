<?php

namespace App\Filament\Resources\EmployeeEvaluations\Pages;

use App\Filament\Resources\EmployeeEvaluations\EmployeeEvaluationResource;
use App\Models\EmployeeEvaluation;
use App\Services\EmployeeEvaluationWorkflowService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use LogicException;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class EditEmployeeEvaluation extends EditRecord
{
    protected static string $resource = EmployeeEvaluationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($this->evaluationRecord()->status, ['Submitted', 'Approved'], true)) {
            return [];
        }

        unset($data['status'], $data['final_score'], $data['submitted_at'], $data['approved_at'], $data['acknowledged_at'], $data['approval_source'], $data['employee_id'], $data['performed_by'], $data['employee_evaluation_form_id'], $data['evaluation_year']);

        /** @var array<int, array<string, mixed>> $originalCriteria */
        $originalCriteria = $this->evaluationRecord()->getAttribute('criteria') ?? [];
        $submittedCriteria = $data['criteria'] ?? [];
        $canonicalCriteria = [];

        foreach ($originalCriteria as $index => $snapshot) {
            $response = is_array($submittedCriteria[$index] ?? null) ? $submittedCriteria[$index] : [];

            foreach (['score', 'answer', 'notes'] as $responseField) {
                if (array_key_exists($responseField, $response)) {
                    $snapshot[$responseField] = $response[$responseField];
                }
            }

            $canonicalCriteria[] = $snapshot;
        }

        $data['criteria'] = $canonicalCriteria;

        return $data;
    }

    private function evaluationRecord(): EmployeeEvaluation
    {
        $record = $this->getRecord();

        if (! $record instanceof EmployeeEvaluation) {
            throw new LogicException('The current record is not an employee evaluation.');
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markInProgress')
                ->label('Mark in progress')
                ->visible(fn (): bool => $this->evaluationRecord()->status === 'Draft')
                ->action(function (): void {
                    app(EmployeeEvaluationWorkflowService::class)->start($this->evaluationRecord());
                    $this->evaluationRecord()->refresh();
                    Notification::make()->title('Evaluation marked in progress')->success()->send();
                }),
            Actions\Action::make('submitEvaluation')
                ->label('Submit evaluation')
                ->requiresConfirmation()
                ->modalDescription('Submitting calculates the final percentage and emails the employee a secure acknowledgment link with a seven-day deadline.')
                ->visible(fn (): bool => in_array($this->evaluationRecord()->status, ['Draft', 'In Progress'], true))
                ->action(function (): void {
                    // Persist and validate the current form state before the workflow reads the evaluation.
                    // Otherwise the service can submit stale criteria instead of the answers on screen.
                    $this->save(false, false);

                    app(EmployeeEvaluationWorkflowService::class)->submit($this->evaluationRecord());

                    Notification::make()
                        ->title('Evaluation submitted')
                        ->body('The score was calculated and the acknowledgment email was issued.')
                        ->success()
                        ->send();

                    $this->redirect(EmployeeEvaluationResource::getUrl('edit', ['record' => $this->evaluationRecord()]));
                }),
            Actions\Action::make('resendAcknowledgment')
                ->label('Resend acknowledgment email')
                ->requiresConfirmation()
                ->modalDescription('This issues a new single-use link and restarts the seven-day acknowledgment window.')
                ->visible(fn (): bool => $this->evaluationRecord()->status === 'Submitted')
                ->action(function (): void {
                    app(EmployeeEvaluationWorkflowService::class)->resendApprovalLink($this->evaluationRecord());

                    Notification::make()
                        ->title('Acknowledgment email sent')
                        ->success()
                        ->send();

                    $this->redirect(EmployeeEvaluationResource::getUrl('edit', ['record' => $this->evaluationRecord()]));
                }),
            Actions\DeleteAction::make(),
            CommentsAction::make(),
        ];
    }
}
