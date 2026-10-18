<?php

namespace App\Filament\Resources\TestSimulations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TestSimulationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Select::make('teacher_id')->relationship('teacher', 'full_name')->searchable()->preload()->required(),
            Select::make('test_id')->relationship('test', 'title')->searchable()->preload(),
            Select::make('group_id')->relationship('group', 'title')->searchable()->preload(),
            DateTimePicker::make('date')->required(),
            TextInput::make('number_of_hours')->numeric()->minValue(0.01)->step(0.01)->required(),
            Select::make('status')->options([
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ])->default('scheduled')->required()->live(),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }
}
