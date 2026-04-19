<?php

namespace App\Filament\Resources\VendorInvoices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use App\Filament\Resources\ProductResources\Actions\ManageProductResourceAction;

class VendorInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('vendorContract.contract_code')
                    ->label('Vendor Contract')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('vat'),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('month'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('invoice'),
                Tables\Columns\TextColumn::make('invoice_number'),
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
                ManageProductResourceAction::make()
                    ->amountField('amount'),
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
