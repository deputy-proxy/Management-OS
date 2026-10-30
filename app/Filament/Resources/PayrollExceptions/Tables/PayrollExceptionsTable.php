<?php

namespace App\Filament\Resources\PayrollExceptions\Tables;

use App\Models\PayrollException;
use App\Services\Payroll\PayrollValidationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollExceptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.full_name')->label('Employee')->searchable(),
            TextColumn::make('period_start')->label('Period')->date()->sortable(),
            TextColumn::make('code')->searchable(),
            TextColumn::make('severity')->badge()->color(fn (string $state): string => $state === PayrollException::SEVERITY_BLOCKING ? 'danger' : 'warning'),
            TextColumn::make('status')->badge(),
            TextColumn::make('message')->wrap(),
            TextColumn::make('resolution_guidance')->label('How to resolve')->wrap(),
            TextColumn::make('detected_at')->dateTime()->sortable(),
            TextColumn::make('resolver.name')->label('Resolved by'),
            TextColumn::make('resolved_at')->dateTime()->sortable(),
            TextColumn::make('resolution_note')->label('Resolution note')->wrap(),
            TextColumn::make('events_count')->counts('events')->label('Audit events'),
        ])->filters([
            SelectFilter::make('severity')->options(['blocking' => 'Blocking', 'warning' => 'Warning']),
            SelectFilter::make('status')->options(['open' => 'Open', 'resolved' => 'Resolved']),
        ])->recordActions([
            Action::make('resolve')
                ->label('Resolve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->schema([Textarea::make('note')->label('Resolution note')->required()->maxLength(2000)])
                ->visible(fn (PayrollException $record): bool => $record->status === PayrollException::STATUS_OPEN && hexa()->can('payroll_exception.resolve'))
                ->authorize(fn (): bool => hexa()->can('payroll_exception.resolve'))
                ->action(fn (PayrollException $record, array $data, PayrollValidationService $service) => $service->resolve(auth()->user(), $record, $data['note'])),
        ])->defaultSort('detected_at', 'desc');
    }
}
