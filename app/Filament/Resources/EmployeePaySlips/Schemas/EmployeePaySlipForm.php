<?php
namespace App\Filament\Resources\EmployeePaySlips\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
class EmployeePaySlipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([                
                Section::make('Employee & Period')
                    ->description('The employee and pay period this payslip covers.')
                    ->schema([
                        Forms\Components\Select::make('employee_id')->relationship('employee', 'full_name')->required()->columnSpanFull(), 
                        Forms\Components\DatePicker::make('month')->label('Pay Month')->displayFormat('F Y')->required(),
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'Open' => 'Open',
                                'Closed' => 'Closed',
                                'Paid' => 'Paid',
                            ])
                        ])
                    ->columns(2),
                Section::make('Payslip Items')
                    ->description('Earnings, deductions, contributions and timesheet entries for this pay period.')
                    ->schema([Builder::make('items')->blocks([Block::make('gross_salary')->label('Gross Salary (Salariu Brut)')->schema([Forms\Components\TextInput::make('amount')->label('Amount (RON)')->numeric()->required(), Forms\Components\Textarea::make('notes')->label('Notes')->columnSpanFull()])->columns(2), Block::make('cas')->label('CAS — Pension Contribution (25%)')->schema([Forms\Components\TextInput::make('base_amount')->label('Calculation Base (RON)')->numeric()->required(), Forms\Components\TextInput::make('amount')->label('CAS Amount (RON)')->numeric()->required(), Forms\Components\Toggle::make('employer_contribution')->label('Includes Employer Contribution')->default(false)])->columns(2), Block::make('cass')->label('CASS — Health Insurance (10%)')->schema([Forms\Components\TextInput::make('base_amount')->label('Calculation Base (RON)')->numeric()->required(), Forms\Components\TextInput::make('amount')->label('CASS Amount (RON)')->numeric()->required()])->columns(2), Block::make('income_tax')->label('Income Tax — Impozit pe Venit (10%)')->schema([Forms\Components\TextInput::make('taxable_base')->label('Taxable Base (RON)')->numeric()->required(), Forms\Components\TextInput::make('amount')->label('Tax Amount (RON)')->numeric()->required(), Forms\Components\Toggle::make('personal_deduction_applied')->label('Personal Deduction Applied (Deducere Personală)')->default(false), Forms\Components\TextInput::make('personal_deduction_amount')->label('Deduction Amount (RON)')->numeric()])->columns(2), Block::make('cam')->label('CAM — Employer Labour Tax (2.25%)')->schema([Forms\Components\TextInput::make('base_amount')->label('Calculation Base (RON)')->numeric()->required(), Forms\Components\TextInput::make('amount')->label('CAM Amount (RON)')->numeric()->required()])->columns(2), Block::make('meal_vouchers')->label('Meal Vouchers (Tichete de Masă)')->schema([Forms\Components\TextInput::make('voucher_count')->label('Number of Vouchers')->numeric()->required(), Forms\Components\TextInput::make('voucher_value')->label('Value per Voucher (RON)')->numeric()->required(), Forms\Components\TextInput::make('total_amount')->label('Total Value (RON)')->numeric()->required()])->columns(3), Block::make('bonus')->label('Bonus / Premium')->schema([Forms\Components\TextInput::make('title')->label('Bonus Title')->required(), Forms\Components\TextInput::make('amount')->label('Gross Amount (RON)')->numeric()->required(), Forms\Components\Select::make('type')->label('Type')->options(['performance' => 'Performance Bonus', 'holiday' => 'Holiday Bonus (Prima de Vacanță)', 'thirteenth' => '13th Month Salary', 'other' => 'Other'])->required(), Forms\Components\Textarea::make('notes')->label('Notes')->columnSpanFull()])->columns(2), Block::make('leave')->label('Leave / Absence (Concediu)')->schema([Forms\Components\Select::make('leave_type')->label('Leave Type')->options(['annual' => 'Annual Leave (Concediu de Odihnă)', 'medical' => 'Medical Leave (Concediu Medical)', 'maternity' => 'Maternity Leave', 'paternity' => 'Paternity Leave', 'unpaid' => 'Unpaid Leave (Concediu Fără Plată)', 'other' => 'Other'])->required(), Forms\Components\TextInput::make('days')->label('Number of Days')->numeric()->required(), Forms\Components\TextInput::make('indemnity_amount')->label('Indemnity Amount (RON)')->numeric()])->columns(3), Block::make('timesheet')->label('Timesheet')->schema([Builder::make('entries')->label('Timesheet Entries')->blocks([Block::make('delivered_class')->label('Delivered Class')->schema([Forms\Components\TextInput::make('subject')->label('Subject / Class Name')->required(), Forms\Components\DatePicker::make('date')->required(), Forms\Components\TextInput::make('hours')->label('Hours')->numeric()->required(), Forms\Components\TextInput::make('group')->label('Group / Class')])->columns(2), Block::make('parents_meeting')->label("Parent's Meeting")->schema([Forms\Components\DatePicker::make('date')->required(), Forms\Components\TextInput::make('hours')->label('Hours')->numeric()->required(), Forms\Components\Textarea::make('notes')->label('Notes')->columnSpanFull()])->columns(2), Block::make('simulation')->label('Simulation / Mock Exam')->schema([Forms\Components\TextInput::make('subject')->label('Subject')->required(), Forms\Components\DatePicker::make('date')->required(), Forms\Components\TextInput::make('hours')->label('Hours')->numeric()->required(), Forms\Components\TextInput::make('group')->label('Group / Class')])->columns(2), Block::make('administrative')->label('Administrative Task')->schema([Forms\Components\TextInput::make('title')->label('Task Title')->required(), Forms\Components\DatePicker::make('date')->required(), Forms\Components\TextInput::make('hours')->label('Hours')->numeric()->required(), Forms\Components\Textarea::make('notes')->label('Notes')->columnSpanFull()])->columns(2), Block::make('training')->label('Training / Professional Development')->schema([Forms\Components\TextInput::make('title')->label('Training Title')->required(), Forms\Components\DatePicker::make('date')->required(), Forms\Components\TextInput::make('hours')->label('Hours')->numeric()->required(), Forms\Components\TextInput::make('provider')->label('Provider / Organiser')])->columns(2), Block::make('other_activity')->label('Other Activity')->schema([Forms\Components\TextInput::make('title')->label('Activity Title')->required(), Forms\Components\DatePicker::make('date')->required(), Forms\Components\TextInput::make('hours')->label('Hours')->numeric()->required(), Forms\Components\Textarea::make('notes')->label('Notes')->columnSpanFull()])->columns(2)])->reorderable()->collapsible()])])->reorderable()->collapsible()->cloneable()]),
                Section::make('Payment')
                    ->description('Total amount and payment dates.')
                    ->schema([Forms\Components\TextInput::make('amount')->numeric()->prefix('RON')->required(), Forms\Components\DatePicker::make('due_on')->required(), Forms\Components\DatePicker::make('paid_on')->required()])
                    ->columns(3)
                
            ]);
    }
}