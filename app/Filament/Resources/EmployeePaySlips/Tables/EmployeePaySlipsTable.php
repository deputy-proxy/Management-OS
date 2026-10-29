<?php

namespace App\Filament\Resources\EmployeePaySlips\Tables;

use App\Filament\Resources\EmployeePaySlips\Actions\RecordPaySlipAdjustmentAction;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractPayrollInput;
use App\Models\EmployeePaySlip;
use App\Models\PayrollPolicy;
use App\Services\Payroll\PayrollCalculationRunService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EmployeePaySlipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('month'),
                Tables\Columns\TextColumn::make('amount')->money('RON'),
                Tables\Columns\TextColumn::make('due_on')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('paid_on')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('calculation_runs_count')->label('Calculation snapshots')->counts('calculationRuns'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('recordCalculationSnapshot')
                    ->label('Record calculation snapshot')
                    ->icon('heroicon-o-camera')
                    ->color('info')
                    ->schema([
                        Select::make('employee_contract_id')
                            ->label('Effective-dated employee contract')
                            ->options(fn (EmployeePaySlip $record): array => EmployeeContract::query()
                                ->where('employee_id', $record->employee_id)->orderByDesc('start_date')->get()
                                ->mapWithKeys(fn (EmployeeContract $contract): array => [$contract->id => '#'.$contract->id.' · '.$contract->contract_type.' · '.$contract->start_date.' to '.$contract->end_date])->all())
                            ->searchable()->required()->live(),
                        Select::make('employee_contract_payroll_input_id')
                            ->label('Verified payroll inputs')
                            ->placeholder('No verified input selected')
                            ->options(fn (Get $get): array => $get('employee_contract_id')
                                ? EmployeeContractPayrollInput::query()->where('employee_contract_id', $get('employee_contract_id'))->where('input_status', 'verified')->orderByDesc('effective_from')->get()
                                    ->mapWithKeys(fn (EmployeeContractPayrollInput $input): array => [$input->id => '#'.$input->id.' · '.$input->effective_from.' to '.($input->effective_until ?? 'open ended')])->all()
                                : []),
                        Select::make('payroll_policy_id')
                            ->label('Versioned payroll policy')
                            ->placeholder('No policy selected')
                            ->options(fn (): array => PayrollPolicy::query()->whereIn('status', ['active', 'retired'])->orderBy('policy_key')->orderByDesc('version')->get()
                                ->mapWithKeys(fn (PayrollPolicy $policy): array => [$policy->id => $policy->policy_key.' v'.$policy->version.' · '.$policy->status])->all()),
                        TextInput::make('idempotency_key')->label('Request key')->default(fn (): string => (string) Str::uuid())->required()->maxLength(128),
                    ])
                    ->modalDescription('Records a versioned input/policy snapshot for this month. Missing or unverified data is recorded as blocked. This schema-only workflow does not calculate statutory pay, deductions, or net amounts.')
                    ->visible(fn (): bool => hexa()->can('payroll_calculation_run.create'))
                    ->action(function (EmployeePaySlip $record, array $data, PayrollCalculationRunService $service): void {
                        $contract = EmployeeContract::query()->where('employee_id', $record->employee_id)->findOrFail($data['employee_contract_id']);
                        $input = filled($data['employee_contract_payroll_input_id'] ?? null)
                            ? EmployeeContractPayrollInput::query()->where('employee_contract_id', $contract->id)->findOrFail($data['employee_contract_payroll_input_id'])
                            : null;
                        $policy = filled($data['payroll_policy_id'] ?? null) ? PayrollPolicy::query()->findOrFail($data['payroll_policy_id']) : null;
                        $start = Carbon::parse((string) $record->month)->startOfMonth();
                        $run = $service->record(auth()->user(), $contract, $start->toDateString(), $start->copy()->endOfMonth()->toDateString(), $data['idempotency_key'], $input, $policy, $record);
                        Notification::make()
                            ->title('Payroll snapshot recorded')
                            ->body($run->run_status === 'blocked' ? 'Blocked: '.implode(', ', (array) $run->blocked_reasons) : 'Inputs and policy were snapshotted. No payroll calculation was performed.')
                            ->color($run->run_status === 'blocked' ? 'warning' : 'success')
                            ->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('payroll_calculation_run.create')),
                Action::make('calculatePayroll')
                    ->label('Calculate with configured policy')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->schema([
                        Select::make('employee_contract_id')
                            ->label('Effective-dated employee contract')
                            ->options(fn (EmployeePaySlip $record): array => EmployeeContract::query()
                                ->where('employee_id', $record->employee_id)->orderByDesc('start_date')->get()
                                ->mapWithKeys(fn (EmployeeContract $contract): array => [$contract->id => '#'.$contract->id.' · '.$contract->contract_type.' · '.$contract->start_date.' to '.$contract->end_date])->all())
                            ->searchable()->required()->live(),
                        Select::make('employee_contract_payroll_input_id')
                            ->label('Verified payroll inputs')
                            ->placeholder('No verified input selected')
                            ->options(fn (Get $get): array => $get('employee_contract_id')
                                ? EmployeeContractPayrollInput::query()->where('employee_contract_id', $get('employee_contract_id'))->where('input_status', 'verified')->orderByDesc('effective_from')->get()
                                    ->mapWithKeys(fn (EmployeeContractPayrollInput $input): array => [$input->id => '#'.$input->id.' · '.$input->effective_from.' to '.($input->effective_until ?? 'open ended')])->all()
                                : []),
                        Select::make('payroll_policy_id')
                            ->label('Versioned payroll policy')
                            ->placeholder('No policy selected')
                            ->options(fn (): array => PayrollPolicy::query()->whereIn('status', ['active', 'retired'])->orderBy('policy_key')->orderByDesc('version')->get()
                                ->mapWithKeys(fn (PayrollPolicy $policy): array => [$policy->id => $policy->policy_key.' v'.$policy->version.' · '.$policy->status])->all()),
                        TextInput::make('idempotency_key')->label('Request key')->default(fn (): string => (string) Str::uuid())->required()->maxLength(128),
                    ])
                    ->modalDescription('Calculates only the arithmetic explicitly declared in the selected versioned policy. Missing configuration blocks the run. This is not a statutory tax or compliance determination.')
                    ->visible(fn (EmployeePaySlip $record): bool => $record->isOpen() && hexa()->can('payroll_calculation_run.create'))
                    ->action(function (EmployeePaySlip $record, array $data, PayrollCalculationRunService $service): void {
                        $contract = EmployeeContract::query()->where('employee_id', $record->employee_id)->findOrFail($data['employee_contract_id']);
                        $input = filled($data['employee_contract_payroll_input_id'] ?? null)
                            ? EmployeeContractPayrollInput::query()->where('employee_contract_id', $contract->id)->findOrFail($data['employee_contract_payroll_input_id'])
                            : null;
                        $policy = filled($data['payroll_policy_id'] ?? null) ? PayrollPolicy::query()->findOrFail($data['payroll_policy_id']) : null;
                        $start = Carbon::parse((string) $record->month)->startOfMonth();
                        $run = $service->calculate(auth()->user(), $contract, $start->toDateString(), $start->copy()->endOfMonth()->toDateString(), $data['idempotency_key'], $input, $policy, $record);
                        $resultRecord = $run->result()->first();
                        $snapshotValue = $resultRecord?->getAttribute('result_snapshot');
                        $snapshot = is_array($snapshotValue) ? $snapshotValue : [];
                        $messages = is_array($snapshot['validation_messages'] ?? null) ? array_column($snapshot['validation_messages'], 'message') : [];
                        $body = $run->run_status === 'calculated'
                            ? 'Gross '.$snapshot['gross_amount'].'; deductions '.$snapshot['deductions_amount'].'; net '.$snapshot['net_amount'].' '.$snapshot['currency_code'].'. Uses configured arithmetic only, not a statutory compliance determination.'
                            : 'Blocked: '.implode(', ', (array) $run->blocked_reasons).' '.implode(' ', $messages);
                        Notification::make()
                            ->title($run->run_status === 'calculated' ? 'Payroll calculation snapshot created' : 'Payroll calculation blocked')
                            ->body($body)
                            ->color($run->run_status === 'calculated' ? 'success' : 'warning')
                            ->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('payroll_calculation_run.create')),
                EditAction::make()->visible(fn (EmployeePaySlip $record): bool => $record->status !== 'Paid'),
                RecordPaySlipAdjustmentAction::make(),
                DeleteAction::make()->visible(fn (EmployeePaySlip $record): bool => $record->isOpen() && ! $record->lineItems()->exists()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
