<?php

namespace App\Filament\Resources\EmployeePaySlips\Actions;

use App\Models\EmployeePaySlip;
use App\Models\EmployeePaySlipItem;
use App\Services\EmployeePaySlipService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class RecordPaySlipAdjustmentAction
{
    public static function make(): Action
    {
        return Action::make('recordAdjustment')
            ->label('Record Adjustment')
            ->icon('heroicon-o-adjustments-horizontal')
            ->visible(fn (EmployeePaySlip $record): bool => hexa()->can('employee_pay_slip.update') && $record->isOpen())
            ->form([
                Select::make('original_item_id')
                    ->label('Original payslip item')
                    ->options(fn (EmployeePaySlip $record): array => EmployeePaySlipItem::query()
                        ->whereHas('paySlip', function ($query) use ($record): void {
                            $query->where('employee_id', $record->employee_id)
                                ->where(function ($period) use ($record): void {
                                    $period->where(function ($older) use ($record): void {
                                        $older->whereDate('month', '<', $record->month)->whereIn('status', ['Closed', 'Paid']);
                                    });
                                    if ($record->isOpen()) {
                                        $period->orWhere(function ($same) use ($record): void {
                                            $same->whereDate('month', '=', $record->month)->where('status', 'Open');
                                        });
                                    }
                                });
                        })
                        ->with('paySlip')
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (EmployeePaySlipItem $item): array => [$item->getKey() => sprintf('%s · %s · %s RON', $item->paySlip->month->format('Y-m'), $item->description, $item->amount)])
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('amount')
                    ->label('Signed Adjustment (RON)')
                    ->numeric()
                    ->step(0.01)
                    ->helperText('Use a positive amount for an underpayment and a negative amount for an overpayment.')
                    ->required(),
                Textarea::make('reason')->required()->maxLength(2000),
            ])
            ->action(function (EmployeePaySlip $record, array $data, EmployeePaySlipService $service): void {
                abort_unless(hexa()->can('employee_pay_slip.update'), 403);

                try {
                    $original = EmployeePaySlipItem::query()->with('paySlip')->findOrFail($data['original_item_id']);
                    $result = $service->addAdjustment($record, $original, (float) $data['amount'], (string) $data['reason']);
                } catch (ValidationException $exception) {
                    Notification::make()->title('Could not record adjustment')->body(collect($exception->errors())->flatten()->first() ?? 'Check the adjustment details.')->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($result['created'] ? 'Adjustment recorded' : 'Adjustment already exists')
                    ->body('The original source item was not modified.')
                    ->success()
                    ->send();
            });
    }
}
