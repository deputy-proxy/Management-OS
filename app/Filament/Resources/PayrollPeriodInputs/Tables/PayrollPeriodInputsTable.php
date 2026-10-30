<?php

namespace App\Filament\Resources\PayrollPeriodInputs\Tables;

use App\Models\PayrollPeriodInput;
use App\Services\Payroll\PayrollPeriodInputService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollPeriodInputsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.full_name')->label('Employee')->searchable(),
            TextColumn::make('period_start')->date()->sortable(),
            TextColumn::make('category')->badge(),
            TextColumn::make('description')->searchable(),
            TextColumn::make('amount')->numeric(2),
            TextColumn::make('status')->badge(),
            TextColumn::make('reviewer.name')->label('Reviewed by'),
        ])->filters([SelectFilter::make('status')->options(array_combine(PayrollPeriodInput::STATUSES, PayrollPeriodInput::STATUSES)), SelectFilter::make('category')->options(array_combine(PayrollPeriodInput::CATEGORIES, PayrollPeriodInput::CATEGORIES))])
            ->recordActions([
                EditAction::make()->visible(fn (PayrollPeriodInput $record): bool => in_array($record->status, ['draft', 'rejected'], true) && hexa()->can('payroll_period_input.update')),
                Action::make('submit')->label('Submit for review')->visible(fn ($record) => in_array($record->status, ['draft', 'rejected'], true) && hexa()->can('payroll_period_input.submit'))->action(fn (PayrollPeriodInput $record, PayrollPeriodInputService $service) => $service->submit(auth()->user(), $record)),
                Action::make('approve')->color('success')->requiresConfirmation()->visible(fn ($record) => $record->status === 'submitted' && hexa()->can('payroll_period_input.approve'))->form([Textarea::make('note')->maxLength(2000)])->action(fn (PayrollPeriodInput $record, array $data, PayrollPeriodInputService $service) => $service->approve(auth()->user(), $record, $data['note'] ?? null)),
                Action::make('reject')->color('danger')->visible(fn ($record) => $record->status === 'submitted' && hexa()->can('payroll_period_input.approve'))->form([Textarea::make('note')->required()->maxLength(2000)])->action(fn (PayrollPeriodInput $record, array $data, PayrollPeriodInputService $service) => $service->reject(auth()->user(), $record, $data['note'])),
            ])->defaultSort('period_start', 'desc');
    }
}
