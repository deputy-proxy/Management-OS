<?php
namespace App\Filament\Resources\EmployeeMedicalLeaves\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class EmployeeMedicalLeaveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Leave Details')
                    ->description('Employee submitting this medical leave request.')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee', 'full_name')
                            ->default(
                                fn () => \App\Models\Employee::where('user_id', auth()->id())->value('id')
                            )
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Leave Period')
                    ->description('The start and end dates of the medical leave.')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Supporting Document')
                    ->description('Attach or reference the medical certificate for this leave.')
                    ->schema([
                        Forms\Components\FileUpload::make('document')
                            ->required()
                            ->columnSpanFull(),
                    ]),

            ]);
    }
}