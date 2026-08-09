<?php

namespace App\Filament\Resources\EmployeeContracts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use Illuminate\Support\Str;
class EmployeeContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('end_date')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('contract_type'),
                Tables\Columns\TextColumn::make('base_salary'),
                Tables\Columns\TextColumn::make('hourly_rate'),
                Tables\Columns\TextColumn::make('clauses')
                    ->label('Clauses')
                    ->formatStateUsing(function ($state): string {
                        if (blank($state) || ! is_array($state)) {
                            return '0 clauses';
                        }

                        // If $state is an associative array representing a single block ['type' => '...', 'data' => [...]]
                        // array_is_list() checks if array keys are sequential 0, 1, 2...
                        if (! array_is_list($state) && isset($state['type'])) {
                            return '1 clause';
                        }

                        // Count only elements that represent a valid block (have a 'type' key)
                        $count = collect($state)
                            ->filter(fn ($item) => is_array($item) && isset($item['type']))
                            ->count();

                        return $count . ' ' . Str::plural('clause', $count);
                    })
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                CommentsAction::make()
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make()
                ])
            ]);
    }
}
