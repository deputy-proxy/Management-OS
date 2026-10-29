<?php
namespace App\Filament\Resources\EmployeeContracts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use App\Models\EmployeeContractPayrollInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class EmployeeContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Contract Parties')
                    ->description('The employee this contract applies to.')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee', 'full_name')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Contract Terms')
                    ->description('Duration and type of employment.')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->required(),
                        Select::make('contract_type')
                            ->required()
                            ->options([
                                'full_time' => 'Full-Time',
                                'part_time' => 'Part-Time',
                                'contractor' => 'Contractor',
                                'intern'     => 'Intern',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Compensation')
                    ->description('Salary and rate details for this contract.')
                    ->schema([
                        Forms\Components\TextInput::make('base_salary')
                            ->minValue(0)
                            ->numeric()
                            ->prefix('RON')
                            ->required(),
                        Forms\Components\TextInput::make('hourly_rate')
                            ->minValue(0)
                            ->numeric()
                            ->prefix('RON')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Effective-dated payroll inputs')
                    ->description('Input records preserve their own effective periods. Blank fields mean unknown, not zero, exempt, or approved. Do not put personal identifiers or medical details in source references.')
                    ->schema([
                        Forms\Components\Repeater::make('payrollInputs')
                            ->relationship()
                            ->schema([
                                Forms\Components\DatePicker::make('effective_from')->required(),
                                Forms\Components\DatePicker::make('effective_until')->afterOrEqual('effective_from'),
                                Forms\Components\Select::make('salary_basis')->options(EmployeeContractPayrollInput::SALARY_BASES)->placeholder('Unknown')->native(false),
                                Forms\Components\TextInput::make('base_amount')->numeric()->minValue(0)->step('0.000001')->helperText('Explicit amount for the configured salary basis. Never inferred from legacy contract salary fields.'),
                                Forms\Components\TextInput::make('currency_code')->label('Currency code')->length(3)->placeholder('RON')->dehydrateStateUsing(fn ($state) => $state === null || trim((string) $state) === '' ? null : strtoupper(trim((string) $state))),
                                Forms\Components\TextInput::make('scheduled_hours_per_week')->numeric()->minValue(0)->maxValue(168)->step('0.25'),
                                Forms\Components\KeyValue::make('work_schedule')->label('Schedule details')->keyLabel('Field')->valueLabel('Value')->addable()->deletable()->reorderable(),
                                Forms\Components\Repeater::make('allowance_inputs')
                                    ->label('Allowance inputs')
                                    ->schema([
                                        Forms\Components\TextInput::make('code')->required()->maxLength(64),
                                        Forms\Components\TextInput::make('amount')->numeric()->minValue(0)->required(),
                                        Forms\Components\TextInput::make('currency_code')->length(3)->required()->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state))),
                                    ])->columns(3)->defaultItems(0)->addActionLabel('Add allowance'),
                                Forms\Components\TextInput::make('tax_status_code')->maxLength(64)->helperText('Optional configured code only. Blank means unknown; this field does not calculate or infer tax treatment.'),
                                Forms\Components\TextInput::make('source_reference')->maxLength(255)->helperText('Reference only. Keep sensitive documents outside this field.'),
                                Forms\Components\Select::make('input_status')
                                    ->options(EmployeeContractPayrollInput::STATUSES)->required()->default('unverified')->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        if ($state === 'verified') {
                                            $set('reviewed_by', auth()->id());
                                            $set('reviewed_at', now()->toDateTimeString());
                                        } else {
                                            $set('reviewed_by', null);
                                            $set('reviewed_at', null);
                                        }
                                    }),
                                Forms\Components\Hidden::make('reviewed_by'),
                                Forms\Components\Hidden::make('reviewed_at'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add effective-dated input set')
                            ->reorderable(false),
                    ]),

                Section::make('Clauses')
                    ->description('Define the legal clauses that apply to this contract.')
                    ->schema([
                        Builder::make('clauses')
                            ->blocks([

                                Block::make('confidentiality')
                                    ->label('Confidentiality & NDA')
                                    ->schema([
                                        Forms\Components\Textarea::make('content')
                                            ->label('Clause Text')
                                            ->required()
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('duration_years')
                                            ->label('Duration After Termination (years)')
                                            ->numeric(),
                                        Forms\Components\CheckboxList::make('scope')
                                            ->label('Scope')
                                            ->options([
                                                'trade_secrets'      => 'Trade Secrets',
                                                'client_data'        => 'Client Data',
                                                'internal_processes' => 'Internal Processes',
                                                'source_code'        => 'Source Code',
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columns(2),

                                Block::make('non_compete')
                                    ->label('Non-Compete')
                                    ->schema([
                                        Forms\Components\Textarea::make('content')
                                            ->label('Clause Text')
                                            ->required()
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('duration_months')
                                            ->label('Duration After Termination (months)')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\TextInput::make('geographic_scope')
                                            ->label('Geographic Scope')
                                            ->placeholder('e.g. Nationwide, EU, specific cities'),
                                    ])
                                    ->columns(2),

                                Block::make('intellectual_property')
                                    ->label('Intellectual Property Assignment')
                                    ->schema([
                                        Forms\Components\Textarea::make('content')
                                            ->label('Clause Text')
                                            ->required()
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('ownership')
                                            ->label('IP Ownership')
                                            ->options([
                                                'company'  => 'Company owns all IP',
                                                'employee' => 'Employee retains IP',
                                                'shared'   => 'Shared ownership',
                                            ])
                                            ->required(),
                                        Forms\Components\Toggle::make('includes_prior_work')
                                            ->label('Includes Prior Work'),
                                    ])
                                    ->columns(2),

                                Block::make('termination')
                                    ->label('Termination')
                                    ->schema([
                                        Forms\Components\Textarea::make('content')
                                            ->label('Clause Text')
                                            ->required()
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('notice_period_days')
                                            ->label('Notice Period (days)')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\Select::make('termination_grounds')
                                            ->label('Grounds for Termination')
                                            ->multiple()
                                            ->options([
                                                'performance'       => 'Performance',
                                                'misconduct'        => 'Misconduct',
                                                'redundancy'        => 'Redundancy',
                                                'mutual_agreement'  => 'Mutual Agreement',
                                                'end_of_contract'   => 'End of Contract',
                                            ]),
                                    ])
                                    ->columns(2),

                                Block::make('remote_work')
                                    ->label('Remote Work Policy')
                                    ->schema([
                                        Forms\Components\Textarea::make('content')
                                            ->label('Clause Text')
                                            ->required()
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('work_mode')
                                            ->label('Work Mode')
                                            ->options([
                                                'fully_remote' => 'Fully Remote',
                                                'hybrid'       => 'Hybrid',
                                                'on_site'      => 'On-Site',
                                            ])
                                            ->required(),
                                        Forms\Components\TextInput::make('remote_days_per_week')
                                            ->label('Remote Days per Week')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(5),
                                    ])
                                    ->columns(2),

                                Block::make('probation')
                                    ->label('Probation Period')
                                    ->schema([
                                        Forms\Components\Textarea::make('content')
                                            ->label('Clause Text')
                                            ->required()
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('duration_months')
                                            ->label('Duration (months)')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\TextInput::make('notice_during_probation_days')
                                            ->label('Notice Period During Probation (days)')
                                            ->numeric(),
                                    ])
                                    ->columns(2),

                                Block::make('use_own_equipment_reimbursement')
                                    ->label('Own Equipment Reimbursement')
                                    ->schema([
                                        Forms\Components\TextInput::make('amount')
                                            ->label('Monthly Reimbursement (RON)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required(),
                                        Forms\Components\Textarea::make('content')
                                            ->label('Clause Text')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Block::make('open_clause')
                                    ->label('Custom Clause')
                                    ->schema([
                                        Forms\Components\TextInput::make('title')
                                            ->label('Clause Title')
                                            ->required(),
                                        Forms\Components\Textarea::make('content')
                                            ->label('Clause Text')
                                            ->required()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                            ])
                            ->reorderable()
                            ->collapsible()
                            ->cloneable(),
                    ]),

            ]);
    }
}