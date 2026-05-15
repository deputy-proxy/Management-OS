<?php
namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Full Name')
                    ->description('The full name is automatically composed from the fields below.')
                    ->schema([
                        Forms\Components\TextInput::make('full_name')
                            ->disabled()
                            ->dehydrated(true)
                            ->placeholder('Will be filled automatically')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn($state, Set $set, Get $get) =>
                                $set('full_name', self::buildFullName(
                                    $get('first_name'),
                                    $get('middle_name'),
                                    $get('last_name')
                                ))
                            ),
                        Forms\Components\TextInput::make('middle_name'),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn($state, Set $set, Get $get) =>
                                $set('full_name', self::buildFullName(
                                    $get('first_name'),
                                    $get('middle_name'),
                                    $get('last_name')
                                ))
                            ),
                        Forms\Components\Select::make('job_description_id')
                            ->relationship('jobDescription', 'job_title')
                            ->label('Position')
                            ->required(),
                    ])
                    ->columns(3),

                Section::make('Personal & Account Details')
                    ->description('Date of birth and the linked system account for this employee.')
                    ->schema([
                        Forms\Components\DatePicker::make('date_of_birth'),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required(),
                    ])
                    ->columns(2),

            ]);
    }

    private static function buildFullName(?string $first, ?string $middle, ?string $last): string
    {
        return collect([$first, $middle, $last])
            ->filter()
            ->implode(' ');
    }
}