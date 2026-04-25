<?php
namespace App\Filament\Resources\Employees\Schemas;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('full_name')
                    ->disabled()
                    ->dehydrated(true)
                    ->placeholder('Will be filled automatically'),

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

                Forms\Components\DatePicker::make('date_of_birth'),

                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
            ]);
    }

    private static function buildFullName(?string $first, ?string $middle, ?string $last): string
    {
        return collect([$first, $middle, $last])
            ->filter()
            ->implode(' ');
    }
}