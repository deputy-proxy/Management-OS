<?php

namespace App\Filament\Resources\PayrollPolicies\Tables;

use App\Models\PayrollPolicy;
use App\Services\Payroll\PayrollPolicyService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PayrollPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('policy_key')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'retired' => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_until')->date()->placeholder('Open-ended'),
                TextColumn::make('configuration_gaps')
                    ->label('Configuration gaps')
                    ->state(fn (PayrollPolicy $record): string => implode(', ', $record->configurationGaps()) ?: 'None')
                    ->wrap(),
            ])
            ->filters([SelectFilter::make('status')->options(PayrollPolicy::STATUSES)])
            ->recordActions([
                EditAction::make()->visible(fn (PayrollPolicy $record): bool => $record->status === 'draft' && hexa()->can('payroll_policy.update')),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PayrollPolicy $record): bool => $record->status === 'draft' && hexa()->can('payroll_policy.activate'))
                    ->requiresConfirmation()
                    ->modalDescription('Activation preserves the version. Any required parameter that is missing will remain a visible gap and must block calculations that depend on it.')
                    ->action(function (PayrollPolicy $record, PayrollPolicyService $service): void {
                        try {
                            $service->activate($record);
                            Notification::make()->title('Payroll policy activated')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Policy could not be activated')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();
                        }
                    }),
                Action::make('retire')
                    ->label('Retire')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn (PayrollPolicy $record): bool => $record->status === 'active' && hexa()->can('payroll_policy.retire'))
                    ->requiresConfirmation()
                    ->action(function (PayrollPolicy $record, PayrollPolicyService $service): void {
                        try {
                            $service->retire($record);
                            Notification::make()->title('Payroll policy retired')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Policy could not be retired')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('policy_key')
            ->persistSortInSession();
    }
}
