<?php

namespace App\Filament\Resources\StudentGroupMemberships\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class StudentGroupMembershipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('student_id')
                ->relationship('student', 'full_name')
                ->searchable()
                ->preload()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->required(),
            Forms\Components\Select::make('group_id')
                ->relationship('group', 'title')
                ->searchable()
                ->preload()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->required(),
            Forms\Components\DatePicker::make('started_on')
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->required()
                ->native(false),
            Forms\Components\DatePicker::make('ended_on')
                ->native(false)
                ->afterOrEqual('started_on')
                ->helperText('Leave blank while this student remains enrolled in this group.'),
        ]);
    }
}