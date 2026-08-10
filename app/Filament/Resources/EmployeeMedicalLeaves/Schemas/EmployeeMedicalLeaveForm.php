<?php
namespace App\Filament\Resources\EmployeeMedicalLeaves\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

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