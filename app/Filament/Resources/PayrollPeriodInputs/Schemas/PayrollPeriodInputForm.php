<?php

namespace App\Filament\Resources\PayrollPeriodInputs\Schemas;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\PayrollPeriodInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollPeriodInputForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Employee and payroll period')->schema([
                Select::make('employee_id')->options(fn (): array => Employee::query()->orderBy('full_name')->pluck('full_name', 'id')->all())->searchable()->required()->live(),
                Select::make('employee_contract_id')->options(fn (callable $get): array => EmployeeContract::query()->when($get('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))->get()->mapWithKeys(fn ($contract) => [$contract->id => '#'.$contract->id.' · '.$contract->contract_type.' · '.$contract->start_date.' – '.$contract->end_date])->all())->searchable()->required(),
                DatePicker::make('period_start')->required(), DatePicker::make('period_end')->required()->afterOrEqual('period_start'),
                DatePicker::make('effective_from')->required(), DatePicker::make('effective_until')->afterOrEqual('effective_from'),
            ])->columns(2),
            Section::make('Input details')->description('Capture source facts only. Statutory proration and overtime calculations remain blocked until specialist-approved policy rules exist.')->schema([
                Select::make('category')->options(array_combine(PayrollPeriodInput::CATEGORIES, array_map(fn ($v) => str($v)->headline()->toString(), PayrollPeriodInput::CATEGORIES)))->required(),
                Select::make('entry_type')->options(['earning' => 'Earning', 'deduction' => 'Deduction', 'informational' => 'Informational'])->required(),
                TextInput::make('description')->required()->maxLength(255),
                TextInput::make('quantity')->numeric()->minValue(0), TextInput::make('unit')->maxLength(32),
                TextInput::make('amount')->numeric()->minValue(0), TextInput::make('currency_code')->maxLength(3)->regex('/^[A-Z]{3}$/'),
                TextInput::make('source_type')->required()->maxLength(100), TextInput::make('source_reference')->required()->maxLength(255),
                Textarea::make('notes')->maxLength(5000)->columnSpanFull(),
            ])->columns(2),
        ]);
    }
}
