<?php

namespace App\Filament\Resources\PaymentAllocations;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\CustomerInvoice;
use App\Models\PaymentAllocation;
use App\Models\Transaction;
use App\Models\VendorInvoice;
use App\Services\Finance\PaymentAllocationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentAllocationResource extends Resource
{
    protected static ?string $model = PaymentAllocation::class;

    protected static ?string $navigationLabel = 'Payment Allocations';

    protected static ?int $navigationSort = 12;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Treasurery & Tax';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('transaction_id')->label('Source payment')->sortable(),
                TextColumn::make('allocation_kind')->label('Type')->badge(),
                TextColumn::make('customerInvoice.identifier')->label('Customer invoice'),
                TextColumn::make('vendorInvoice.identifier')->label('Vendor invoice'),
                TextColumn::make('amount_decimal')->label('Amount')->sortable(),
                TextColumn::make('currency_code')->label('Currency'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('reason')->limit(55),
                TextColumn::make('creator.name')->label('Created by'),
                TextColumn::make('confirmed_at')->dateTime()->label('Confirmed'),
                TextColumn::make('reversed_at')->dateTime()->label('Reversed'),
                TextColumn::make('rejection_reason')->label('Rejection reason')->limit(55),
            ])
            ->headerActions([
                Action::make('proposeAllocation')
                    ->label('Propose allocation')
                    ->schema([
                        Select::make('transaction_id')
                            ->label('Source payment / transaction')
                            ->options(fn (): array => Transaction::query()
                                ->whereNotNull('amount_decimal')
                                ->whereNotNull('currency_code')
                                ->orderByDesc('id')
                                ->limit(200)
                                ->get()
                                ->mapWithKeys(fn (Transaction $transaction): array => [
                                    $transaction->id => '#'.$transaction->id.' '.$transaction->transaction_type.' '.$transaction->getAttribute('amount_decimal').' '.$transaction->getAttribute('currency_code'),
                                ])->all())
                            ->searchable()
                            ->required(),
                        Select::make('allocation_kind')
                            ->label('Allocation type')
                            ->options(['invoice' => 'Invoice allocation', 'fee' => 'Payment fee'])
                            ->required()
                            ->live(),
                        Select::make('target_type')
                            ->label('Invoice type')
                            ->options(['customer_invoice' => 'Customer invoice', 'vendor_invoice' => 'Vendor invoice'])
                            ->visible(fn ($get): bool => $get('allocation_kind') === 'invoice')
                            ->required(fn ($get): bool => $get('allocation_kind') === 'invoice')
                            ->live(),
                        Select::make('target_id')
                            ->label('Invoice')
                            ->options(function ($get): array {
                                return match ($get('target_type')) {
                                    'customer_invoice' => CustomerInvoice::query()->whereNotNull('currency_code')->orderByDesc('id')->limit(200)->get()->mapWithKeys(fn (CustomerInvoice $invoice): array => [
                                        $invoice->id => '#'.$invoice->id.' '.$invoice->identifier.' '.$invoice->getAttribute('currency_code'),
                                    ])->all(),
                                    'vendor_invoice' => VendorInvoice::query()->whereNotNull('currency_code')->orderByDesc('id')->limit(200)->get()->mapWithKeys(fn (VendorInvoice $invoice): array => [
                                        $invoice->id => '#'.$invoice->id.' '.$invoice->identifier.' '.$invoice->getAttribute('currency_code'),
                                    ])->all(),
                                    default => [],
                                };
                            })
                            ->searchable()
                            ->visible(fn ($get): bool => $get('allocation_kind') === 'invoice')
                            ->required(fn ($get): bool => $get('allocation_kind') === 'invoice'),
                        TextInput::make('amount_decimal')
                            ->label('Allocation amount')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        TextInput::make('currency_code')
                            ->label('Currency (ISO 4217)')
                            ->length(3)
                            ->required(),
                        Textarea::make('reason')
                            ->label('Reason / evidence')
                            ->required()
                            ->minLength(1)
                            ->maxLength(1000),
                    ])
                    ->action(function (array $data, PaymentAllocationService $service): void {
                        $service->propose(
                            auth()->user(),
                            (int) $data['transaction_id'],
                            $data['allocation_kind'],
                            (string) $data['amount_decimal'],
                            strtoupper($data['currency_code']),
                            $data['target_type'] ?? null,
                            isset($data['target_id']) ? (int) $data['target_id'] : null,
                            $data['reason'],
                        );
                        Notification::make()->title('Allocation proposal recorded')->body('Proposal is not included in payment totals until confirmed.')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('transaction.update')),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirm allocation only')
                    ->modalDescription('This confirms the reviewed payment allocation only. It does not settle an invoice or change its balance. Invoice-side balance validation is unresolved until Finance approves canonical invoice-total semantics.')
                    ->color('success')
                    ->visible(fn (PaymentAllocation $record): bool => $record->status === PaymentAllocation::STATUS_PROPOSED)
                    ->action(function (PaymentAllocation $record, PaymentAllocationService $service): void {
                        $summary = $service->paymentSummary(Transaction::query()->findOrFail($record->transaction_id));
                        $service->confirm($record->id, auth()->user());
                        Notification::make()->title('Allocation confirmed')->body('Source payment status before this decision: '.$summary['status'].'. Invoice balance validation remains subject to approved invoice semantics.')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('transaction.update')),
                Action::make('reject')
                    ->label('Reject proposal')
                    ->color('danger')
                    ->schema([Textarea::make('reason')->label('Rejection reason')->required()->minLength(1)->maxLength(1000)])
                    ->visible(fn (PaymentAllocation $record): bool => $record->status === PaymentAllocation::STATUS_PROPOSED)
                    ->action(function (PaymentAllocation $record, array $data, PaymentAllocationService $service): void {
                        $service->reject($record->id, auth()->user(), $data['reason']);
                        Notification::make()->title('Allocation proposal rejected')->body('No payment or invoice records were changed.')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('transaction.update')),
                Action::make('reverse')
                    ->label('Reverse')
                    ->color('danger')
                    ->schema([Textarea::make('reason')->label('Reversal reason')->required()->minLength(1)->maxLength(1000)])
                    ->visible(fn (PaymentAllocation $record): bool => $record->status === PaymentAllocation::STATUS_CONFIRMED)
                    ->action(function (PaymentAllocation $record, array $data, PaymentAllocationService $service): void {
                        $service->reverse($record->id, auth()->user(), $data['reason']);
                        Notification::make()->title('Allocation reversed')->body('The source transaction and invoice were not modified.')->success()->send();
                    })
                    ->authorize(fn (): bool => hexa()->can('transaction.update')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPaymentAllocations::route('/')];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('transaction.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('Use the audited allocation proposal action.');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Allocation corrections must use confirm or reverse actions.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Allocation history is retained; reverse confirmed allocations instead.');
    }
}
