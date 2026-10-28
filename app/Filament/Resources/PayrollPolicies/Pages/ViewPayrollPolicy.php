<?php

namespace App\Filament\Resources\PayrollPolicies\Pages;

use App\Filament\Resources\PayrollPolicies\PayrollPolicyResource;
use App\Models\PayrollPolicy;
use App\Services\Payroll\PayrollPolicyService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewPayrollPolicy extends ViewRecord
{
    protected static string $resource = PayrollPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => $this->getRecord() instanceof PayrollPolicy
                && $this->getRecord()->status === PayrollPolicy::STATUS_DRAFT
                && hexa()->can('payroll_policy.update')),
            Action::make('createNextVersion')
                ->label('Create next draft version')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => $this->getRecord() instanceof PayrollPolicy
                    && hexa()->can('payroll_policy.create')
                    && $this->getRecord()->status !== PayrollPolicy::STATUS_DRAFT)
                ->requiresConfirmation()
                ->action(function (PayrollPolicyService $service): void {
                    $record = $this->getRecord();
                    if (! $record instanceof PayrollPolicy) {
                        return;
                    }

                    try {
                        $next = $service->createNextVersion($record);
                        Notification::make()
                            ->title('Draft policy version created')
                            ->body('Version '.$next->version.' is ready to edit.')
                            ->success()
                            ->send();
                        $this->redirect(PayrollPolicyResource::getUrl('edit', ['record' => $next]));
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Draft version could not be created')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
