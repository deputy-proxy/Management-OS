<?php

namespace App\Filament\Resources\EmployeePaySlips\Actions;

use App\Models\Session;
use App\Models\SessionAssistance;
use App\Models\TestSimulation;
use App\Services\EmployeePaySlipService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AddToPaySlipAction
{
    public static function make(): Action
    {
        return Action::make('addToPaySlip')
            ->label('Add to Pay Slip')
            ->icon('heroicon-o-banknotes')
            ->requiresConfirmation()
            ->visible(fn (Model $record): bool => hexa()->can('employee_pay_slip.update') && static::supports($record))
            ->action(function (Model $record, EmployeePaySlipService $service): void {
                abort_unless(hexa()->can('employee_pay_slip.update'), 403);

                try {
                    $result = match (true) {
                        $record instanceof Session => $service->addSession($record),
                        $record instanceof SessionAssistance => $service->addSessionAssistance($record),
                        $record instanceof TestSimulation => $service->addTestSimulation($record),
                        default => throw ValidationException::withMessages(['source' => 'This record type cannot be added to payroll.']),
                    };
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Could not add item to payslip')
                        ->body(collect($exception->errors())->flatten()->first() ?? 'The source record is not eligible for payroll.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($result['created'] ? 'Added to payslip' : 'Already on payslip')
                    ->body($result['created'] ? $result['item']->description.' was added with a saved rate snapshot.' : 'This source record is already linked to its payslip; no duplicate was created.')
                    ->success()
                    ->send();
            });
    }

    private static function supports(Model $record): bool
    {
        if ($record instanceof TestSimulation) {
            return strtolower((string) $record->status) === 'completed';
        }

        if ($record instanceof SessionAssistance) {
            return in_array(strtolower(trim((string) $record->status)), ['complete', 'completed'], true)
                && $record->session !== null
                && ! in_array(strtolower((string) $record->session->status), ['cancelled', 'canceled', 'cancel'], true);
        }

        if ($record instanceof Session) {
            return ! in_array(strtolower((string) $record->status), ['cancelled', 'canceled', 'cancel'], true);
        }

        return false;
    }
}
