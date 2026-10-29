<?php

namespace App\Filament\Resources\VendorInvoices\Tables;

use App\Filament\Resources\ProductResources\Actions\ManageProductResourceAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use App\Models\VendorInvoice;
use App\Services\Contractors\ContractorInvoiceLifecycleService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;

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
                Tables\Columns\TextColumn::make('workflow_status')->label('Contractor workflow')->badge(),
                Tables\Columns\TextColumn::make('tax_treatment_status')->label('Tax treatment')->badge(),
                Tables\Columns\TextColumn::make('invoice'),
                Tables\Columns\TextColumn::make('invoice_number'),
                Tables\Columns\TextColumn::make('identifier'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('submitForReview')
                    ->label('Submit for review')
                    ->color('info')
                    ->visible(fn (VendorInvoice $record): bool => in_array($record->workflow_status, ['unresolved', 'rejected'], true))
                    ->action(function (VendorInvoice $record, ContractorInvoiceLifecycleService $service): void {
                        $service->submit($record->id, auth()->user());
                        Notification::make()->title('Invoice submitted for review')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('vendor_invoice.update')),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->schema([Textarea::make('reason')->label('Review decision / evidence')->required()->minLength(1)->maxLength(1000)])
                    ->visible(fn (VendorInvoice $record): bool => $record->workflow_status === 'submitted')
                    ->action(function (VendorInvoice $record, array $data, ContractorInvoiceLifecycleService $service): void {
                        $service->approve($record->id, auth()->user(), $data['reason']);
                        Notification::make()->title('Invoice approved')->body('No tax calculation was performed; a rule reference does not assert statutory compliance.')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('vendor_invoice.update')),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->schema([Textarea::make('reason')->label('Rejection reason')->required()->minLength(1)->maxLength(1000)])
                    ->visible(fn (VendorInvoice $record): bool => $record->workflow_status === 'submitted')
                    ->action(function (VendorInvoice $record, array $data, ContractorInvoiceLifecycleService $service): void {
                        $service->reject($record->id, auth()->user(), $data['reason']);
                        Notification::make()->title('Invoice rejected')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('vendor_invoice.update')),
                Action::make('markPayable')
                    ->label('Mark payable')
                    ->color('success')
                    ->schema([
                        Textarea::make('reason')->label('Payable decision / evidence')->required()->minLength(1)->maxLength(1000),
                        DatePicker::make('payable_on')->label('Payable date'),
                    ])
                    ->visible(fn (VendorInvoice $record): bool => $record->workflow_status === 'approved')
                    ->action(function (VendorInvoice $record, array $data, ContractorInvoiceLifecycleService $service): void {
                        $service->markPayable($record->id, auth()->user(), $data['reason'], $data['payable_on'] ?? null);
                        Notification::make()->title('Invoice marked payable')->body('This does not assert that payment has settled.')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('vendor_invoice.update')),
                Action::make('reverseInvoice')
                    ->label('Reverse invoice')
                    ->color('danger')
                    ->schema([Textarea::make('reason')->label('Reversal reason')->required()->minLength(1)->maxLength(1000)])
                    ->visible(fn (VendorInvoice $record): bool => in_array($record->workflow_status, ['approved', 'payable'], true))
                    ->action(function (VendorInvoice $record, array $data, ContractorInvoiceLifecycleService $service): void {
                        $service->reverse($record->id, auth()->user(), $data['reason']);
                        Notification::make()->title('Invoice reversed')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('vendor_invoice.update')),
                Action::make('correctInvoice')
                    ->label('Create correction')
                    ->color('warning')
                    ->schema([
                        TextInput::make('invoice_number')->label('Correction invoice number')->required()->maxLength(255),
                        TextInput::make('identifier')->label('Correction source identifier')->required()->maxLength(255),
                        TextInput::make('invoice')->label('Correction document reference')->required()->maxLength(255),
                        TextInput::make('amount')->numeric()->label('Corrected legacy amount'),
                        TextInput::make('amount_without_vat')->numeric()->label('Corrected legacy net amount'),
                        TextInput::make('vat')->numeric()->label('Corrected legacy VAT amount'),
                        Textarea::make('reason')->label('Correction reason')->required()->minLength(1)->maxLength(1000),
                    ])
                    ->visible(fn (VendorInvoice $record): bool => in_array($record->workflow_status, ['approved', 'payable'], true))
                    ->action(function (VendorInvoice $record, array $data, ContractorInvoiceLifecycleService $service): void {
                        $reason = $data['reason'];
                        unset($data['reason']);
                        $fields = array_filter($data, fn ($value): bool => $value !== null && $value !== '');
                        $service->correct($record->id, auth()->user(), $fields, $reason);
                        Notification::make()->title('Correction invoice created')->body('The original remains in history. Submit the correction for review separately.')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('vendor_invoice.update')),
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
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
