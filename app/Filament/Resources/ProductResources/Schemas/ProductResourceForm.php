<?php

namespace App\Filament\Resources\ProductResources\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\VendorInvoice;
use App\Models\EmployeeSalary;

class ProductResourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),

                Forms\Components\Select::make('resourceable_type')
                    ->label('Resource Type')
                    ->options([
                        'vendor_invoice'   => 'Vendor Invoice',
                        'employee_salary'  => 'Employee Salary',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('resourceable_id', null);
                        $set('amount', null);
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
                            'vendor_invoice'  => VendorInvoice::query()
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
                        $id   = $get('resourceable_id');

                        if (! $type || ! $id) {
                            $set('amount', null);
                            return;
                        }

                        $record = match ($type) {
                            'vendor_invoice'  => VendorInvoice::find($id),
                            'employee_salary' => EmployeeSalary::find($id),
                            default           => null,
                        };

                        $amount = match ($type) {
                            'vendor_invoice'  => $record?->amount,
                            'employee_salary' => $record?->amount,
                            default           => null,
                        };

                        $set('amount', $amount);
                    }),

                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->readOnly()
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