<?php

namespace App\Filament\Resources\EmployeePaySlips\Tables;

use App\Filament\Resources\EmployeePaySlips\Actions\RecordPaySlipAdjustmentAction;
use App\Models\EmployeePaySlip;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Table;

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
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
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
