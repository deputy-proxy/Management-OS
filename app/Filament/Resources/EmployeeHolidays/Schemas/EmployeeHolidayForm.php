<?php
namespace App\Filament\Resources\EmployeeHolidays\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class EmployeeHolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Request Details')
                    ->description('Employee and current status of this holiday request.')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee', 'full_name')
                            ->default(
                                fn () => \App\Models\Employee::where('user_id', auth()->id())->value('id')
                            )
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'Pending' => 'Pending',
                                'Approved' => 'Approved',
                                'Rejected' => 'Rejected',
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Holiday Period')
                    ->description('The start and end dates for this holiday request.')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->required(),
                    ])
                    ->columns(2),

            ]);
    }
}