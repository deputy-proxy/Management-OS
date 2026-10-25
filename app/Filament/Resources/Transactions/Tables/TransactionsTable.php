<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Filament\Resources\Tasks\Actions\TasksAction;
use App\Models\CustomerInvoice;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use App\Services\Finance\PaymentAllocationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('financialAccount.title')
                    ->label('Financial Account')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('vendorInvoice.id')
                    ->label('Vendor Invoice')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('transaction_type'),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('amount_decimal')->label('Canonical amount')->placeholder('Unresolved'),
                Tables\Columns\TextColumn::make('currency_code')->label('Currency')->placeholder('Unresolved'),
                Tables\Columns\TextColumn::make('allocation_reconciliation')
                    ->label('Payment allocation reconciliation')
                    ->state(function (Transaction $record): string {
                        $summary = app(PaymentAllocationService::class)->paymentSummary($record);

                        return $summary['status'].' | invoice '.$summary['invoice_allocated'].' | fees '.$summary['fees'].' | unallocated '.($summary['unallocated'] ?? 'needs review').' '.($summary['currency_code'] ?? '');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('payment_method'),
                Tables\Columns\TextColumn::make('timestamp')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('customerInvoice.identifier')
                    ->label('Customer Invoice')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('tax.id')
                    ->label('Tax')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('proposePaymentAllocation')
                    ->label('Propose allocation')
                    ->schema([
                        Select::make('allocation_kind')
                            ->label('Allocation type')
                            ->options(['invoice' => 'Invoice allocation', 'fee' => 'Payment fee'])
                            ->required()
                            ->live(),
                        Select::make('target_type')
                            ->label('Invoice type')
                            ->options(fn (Transaction $record): array => match ($record->getAttribute('transaction_type_code')) {
                                'money_in' => ['customer_invoice' => 'Customer invoice'],
                                'money_out' => ['vendor_invoice' => 'Vendor invoice'],
                                default => [],
                            })
                            ->visible(fn ($get): bool => $get('allocation_kind') === 'invoice')
                            ->required(fn ($get): bool => $get('allocation_kind') === 'invoice')
                            ->live(),
                        Select::make('target_id')
                            ->label('Invoice')
                            ->options(function ($get): array {
                                return match ($get('target_type')) {
                                    'customer_invoice' => CustomerInvoice::query()->whereNotNull('currency_code')->orderByDesc('id')->limit(200)->get()->mapWithKeys(fn (CustomerInvoice $invoice): array => [$invoice->id => '#'.$invoice->id.' '.$invoice->identifier.' '.$invoice->getAttribute('currency_code')])->all(),
                                    'vendor_invoice' => VendorInvoice::query()->whereNotNull('currency_code')->orderByDesc('id')->limit(200)->get()->mapWithKeys(fn (VendorInvoice $invoice): array => [$invoice->id => '#'.$invoice->id.' '.$invoice->identifier.' '.$invoice->getAttribute('currency_code')])->all(),
                                    default => [],
                                };
                            })
                            ->searchable()
                            ->visible(fn ($get): bool => $get('allocation_kind') === 'invoice')
                            ->required(fn ($get): bool => $get('allocation_kind') === 'invoice'),
                        TextInput::make('amount_decimal')->label('Amount')->numeric()->minValue(0.01)->required(),
                        TextInput::make('currency_code')->label('Currency')->length(3)->default(fn (Transaction $record): ?string => $record->getAttribute('currency_code'))->required(),
                        Textarea::make('reason')->required()->minLength(1)->maxLength(1000),
                    ])
                    ->action(function (Transaction $record, array $data, PaymentAllocationService $service): void {
                        $service->propose(auth()->user(), $record->id, $data['allocation_kind'], (string) $data['amount_decimal'], strtoupper($data['currency_code']), $data['target_type'] ?? null, isset($data['target_id']) ? (int) $data['target_id'] : null, $data['reason']);
                        Notification::make()->title('Allocation proposal recorded')->body('Confirm it in Payment Allocations to include it in reconciled totals.')->success()->send();
                    })
                    ->visible(fn (Transaction $record): bool => $record->getAttribute('amount_decimal') !== null && $record->getAttribute('currency_code') !== null)
                    ->authorize(fn (): bool => hexa()->can('transaction.update')),
                EditAction::make(),
                DeleteAction::make(),
                TasksAction::make(),
                CommentsAction::make(),
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
