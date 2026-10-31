<?php

namespace App\Filament\Resources\ProductResources\Schemas;

use App\Models\EmployeeSalary;
use App\Models\VendorInvoice;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductResourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),

                Forms\Components\Select::make('cost_type')
                    ->label('Cost classification')
                    ->options([
                        'direct' => 'Direct cost',
                        'overhead' => 'Overhead',
                    ])
                    ->helperText('Legacy resources remain unclassified until explicitly reviewed. Any unallocated overhead remainder stays visible.')
                    ->required(),

                Forms\Components\Select::make('resourceable_type')
                    ->label('Resource Type')
                    ->options([
                        'vendor_invoice' => 'Vendor Invoice',
                        'employee_salary' => 'Employee Salary',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('resourceable_id', null);
                        $set('amount', null);
                        $set('amount_decimal', null);
                        $set('currency_code', null);
                    }),

                Forms\Components\Select::make('resourceable_id')
                    ->label('Resource')
                    ->required()
                    ->options(function (Get $get) {
                        $type = $get('resourceable_type');

                        if (! $type) {
                            return [];
                        }

                        return match ($type) {
                            'vendor_invoice' => VendorInvoice::query()
                                ->get()
                                ->mapWithKeys(fn ($invoice) => [
                                    $invoice->id => "{$invoice->vendor->name} — {$invoice->month->format('M Y')}",
                                ]),
                            'employee_salary' => EmployeeSalary::query()
                                ->get()
                                ->mapWithKeys(fn ($salary) => [
                                    $salary->id => "{$salary->employee->name} — {$salary->month->format('M Y')}",
                                ]),
                            default => [],
                        };
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $type = $get('resourceable_type');
                        $id = $get('resourceable_id');

                        $set('amount_decimal', null);
                        $set('currency_code', null);

                        if (! $type || ! $id) {
                            $set('amount', null);

                            return;
                        }

                        $record = match ($type) {
                            'vendor_invoice' => VendorInvoice::find($id),
                            'employee_salary' => EmployeeSalary::find($id),
                            default => null,
                        };

                        $amount = match ($type) {
                            'vendor_invoice' => $record?->amount,
                            'employee_salary' => $record?->amount,
                            default => null,
                        };

                        $set('amount', $amount);
                    }),

                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->readOnly()
                    ->required(),

                Forms\Components\TextInput::make('amount_decimal')
                    ->label('Canonical source amount')
                    ->numeric()
                    ->helperText('Enter the exact source amount, including a negative adjustment when present. Legacy integer units are not inferred and no currency conversion is applied.')
                    ->required(),

                Forms\Components\TextInput::make('currency_code')
                    ->label('Source currency')
                    ->length(3)
                    ->maxLength(3)
                    ->dehydrateStateUsing(fn ($state) => strtoupper($state))
                    ->required(),

                Forms\Components\TextInput::make('allocated_percentage')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\DatePicker::make('month')
                    ->required(),
            ]);
    }
}
