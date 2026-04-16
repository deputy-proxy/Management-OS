<?php

namespace App\Filament\Resources\GroupSessions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
class GroupSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('group_id')
                    ->relationship('group', 'title')
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateIdentifier($get, $set))
                    ->required(),
                Forms\Components\Select::make('course_id')
                    ->relationship('course', 'title')
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateIdentifier($get, $set))
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
                Forms\Components\DateTimePicker::make('start_on')
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateIdentifier($get, $set))
                    ->required(),
                Forms\Components\DateTimePicker::make('end_on')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('identifier')
                    ->disabled()
                    ->dehydrated()
                    ->readOnly(),
            ]);
    }

    private static function updateIdentifier(Get $get, Set $set): void
    {
        $courseId = $get('course_id');
        $groupId = $get('group_id');
        $startOn = $get('start_on');

        if (!$courseId || !$groupId || !$startOn) {
            return;
        }

        $course = \App\Models\Course::find($courseId);
        $group = \App\Models\Group::find($groupId);
        $date = \Carbon\Carbon::parse($startOn)->format('d/m/Y H:i');

        $set('identifier', "{$course->title} - {$group->title} - {$date}");
    }
}
