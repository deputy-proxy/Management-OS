<?php

namespace App\Filament\Resources\VendorInvoices\Schemas;

use App\Models\Vendor;
use App\Models\WorkerEngagement;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Reference')
                    ->description('Unique identifiers and document references for this invoice.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('identifier')
                            ->required(),
                        Forms\Components\TextInput::make('invoice_number')
                            ->required(),
                        Forms\Components\TextInput::make('invoice')
                            ->required(),
                    ]),

                Section::make('Vendor & Contract')
                    ->description('The vendor and contract this invoice is associated with.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->required(),
                        Forms\Components\Select::make('vendor_contract_id')
                            ->relationship('vendorContract', 'contract_code')
                            ->required(),
                        Forms\Components\Select::make('worker_engagement_id')
                            ->label('PFA contractor engagement')
                            ->options(fn (): array => WorkerEngagement::query()->where('engagement_type', 'pfa_contractor')->where('status', 'active')->with('vendor:id,name')->get()->mapWithKeys(function (WorkerEngagement $engagement): array {
                                /** @var Vendor|null $vendor */
                                $vendor = $engagement->vendor;

                                return [$engagement->id => ($vendor->name ?? 'Vendor #'.$engagement->vendor_id).' · engagement #'.$engagement->id];
                            })->all())
                            ->searchable()->preload()
                            ->helperText('Required for the contractor review workflow. Employee payroll is separate.'),
                    ]),

                Section::make('Financial Details')
                    ->description('Amount, VAT and currency breakdown for this invoice.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('vat')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('currency')
                            ->required(),
                    ]),

                Section::make('Billing Period & Status')
                    ->description('The billing month and current processing status of the invoice.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('month')
                            ->required(),
                        Forms\Components\TextInput::make('status')
                            ->required(),
                        Forms\Components\TextInput::make('workflow_status')
                            ->disabled()->dehydrated(false)
                            ->helperText('Lifecycle status is changed only through the authorized review service.'),
                        Forms\Components\TextInput::make('tax_treatment_status')
                            ->disabled()->dehydrated(false)
                            ->helperText('Tax treatment remains unsupported until a reviewed rule is configured.'),
                    ]),
            ]);
    }
}
