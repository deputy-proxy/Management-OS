<?php

namespace App\Filament\Resources\EmployeePaySlips\Actions;

use App\Models\EmployeePaySlip;
use App\Models\EmployeePaySlipItem;
use App\Services\Payroll\PayrollPeriodLifecycleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RecordPaySlipReversalAction
{
    public static function make(): Action
    {
        return Action::make('reverseOriginalItem')
            ->label('Reverse original item')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (EmployeePaySlip $record): bool => $record->isOpen() && hexa()->can('payroll_period.correct'))
            ->form([
                Select::make('original_item_id')
                    ->label('Original item from a closed period')
                    ->options(fn (EmployeePaySlip $record): array => EmployeePaySlipItem::query()
                        ->whereHas('paySlip', fn ($query) => $query
                            ->where('employee_id', $record->employee_id)
                            ->whereDate('month', '<', $record->month)
                            ->whereIn('status', ['Closed', 'Paid']))
                        ->with('paySlip')
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (EmployeePaySlipItem $item): array => [$item->getKey() => sprintf('%s · %s · %s RON', Carbon::parse((string) $item->paySlip->getAttribute('month'))->format('Y-m'), $item->description, $item->amount)])
                        ->all())
                    ->searchable()
                    ->required(),
                Textarea::make('reason')->label('Reversal reason')->required()->maxLength(2000),
            ])
            ->action(function (EmployeePaySlip $record, array $data, PayrollPeriodLifecycleService $service): void {
                abort_unless(hexa()->can('payroll_period.correct'), 403);

                try {
                    $original = EmployeePaySlipItem::query()->findOrFail($data['original_item_id']);
                    $result = $service->reverse(auth()->user(), $record, $original, (string) $data['reason']);
                } catch (ValidationException $exception) {
                    Notification::make()->title('Could not reverse source item')->body(collect($exception->errors())->flatten()->first() ?? 'Check the reversal details.')->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($result['created'] ? 'Reversal recorded' : 'Reversal already exists')
                    ->body('A signed opposite-value adjustment was added. The original item and closed-period snapshot remain unchanged.')
                    ->success()
                    ->send();
            });
    }
}
