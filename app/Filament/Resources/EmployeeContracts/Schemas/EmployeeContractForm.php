<?php
namespace App\Filament\Resources\EmployeeContracts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
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
                        Forms\Components\TextInput::make('contract_type')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Compensation')
                    ->description('Salary and rate details for this contract.')
                    ->schema([
                        Forms\Components\TextInput::make('base_salary')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('hourly_rate')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                    ])
                    ->columns(2),

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