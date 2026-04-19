<?php

namespace App\Filament\Resources\CustomerInvoices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use App\Filament\Resources\ProductRevenues\Actions\ManageProductRevenueAction;

class CustomerInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('customerContract.contract_code')
                    ->label('Customer Contract')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount_without_vat'),
                Tables\Columns\TextColumn::make('vat'),
                Tables\Columns\TextColumn::make('month'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('invoice'),
                Tables\Columns\TextColumn::make('identifier')
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                CommentsAction::make(),
                ManageProductRevenueAction::make(),

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
