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
                        if (blank($state)) {
                            return '0 clauses';
                        }

                        $items = collect($state);

                        // If Filament wrapped the state under a 'blocks' or 'data' key
                        if ($items->has('blocks') && is_array($items->get('blocks'))) {
                            $items = collect($items->get('blocks'));
                        }

                        // Count items that have a 'type' key (standard Builder block structure)
                        $count = $items->filter(fn ($item) => is_array($item) && isset($item['type']))->count();

                        // Fallback count in case array structure is direct list without 'type'
                        if ($count === 0 && $items->isList()) {
                            $count = $items->count();
                        }

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
