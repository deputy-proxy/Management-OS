<?php

namespace App\Filament\Resources\EmployeeHolidays\Schemas;

use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                            ->relationship(
                                name: 'employee',
                                titleAttribute: 'full_name',
                                modifyQueryUsing: function (Builder $query) {
                                    // Get current logged-in user's employee record along with their job description
                                    $currentEmployee = Employee::with('jobDescription')
                                        ->where('user_id', auth()->id())
                                        ->first();

                                    $currentEmployeeId = $currentEmployee?->id;
                                    $jobTitle = $currentEmployee?->jobDescription?->job_title;

                                    // 1. Owner: can select themselves and any other employee
                                    if ($jobTitle === 'Owner') {
                                        return $query;
                                    }

                                    // 2. General Director: select self and any employee EXCEPT Owner
                                    if ($jobTitle === 'General Director') {
                                        return $query->whereHas('jobDescription', function (Builder $q) {
                                            $q->where('job_title', '!=', 'Owner');
                                        });
                                    }

                                    // 3. Academic Director: select self and any employee with job_title 'Teacher'
                                    if ($jobTitle === 'Academic Director') {
                                        return $query->where(function (Builder $q) use ($currentEmployeeId) {
                                            $q->whereHas('jobDescription', function (Builder $jobQuery) {
                                                $jobQuery->where('job_title', 'Teacher');
                                            });

                                            if ($currentEmployeeId) {
                                                $q->orWhere('id', $currentEmployeeId);
                                            }
                                        });
                                    }

                                    // 4. Anything else: can only select themselves
                                    return $query->where('id', $currentEmployeeId);
                                }
                            )
                            ->default(
                                fn () => Employee::where('user_id', auth()->id())->value('id')
                            )
                            ->searchable()
                            ->preload()
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
                        Forms\Components\TextInput::make('hours_per_day')
                            ->label('Paid Hours per Weekday')
                            ->numeric()
                            ->minValue(0)
                            ->default(8)
                            ->required(),
                    ])
                    ->columns(2),

            ]);
    }
}