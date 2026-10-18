<?php

namespace App\Filament\Resources\Sessions\Schemas;

use App\Models\Course;
use App\Models\Group;
use App\Models\Session;
use App\Services\Academic\SessionLifecycleService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('session_type')
                ->label('Session type')
                ->options(fn (string $operation): array => static::sessionTypeOptions($operation))
                ->default(fn (): string => hexa()->can('session.create') || hexa()->can('group_session.create') ? Session::TYPE_GROUP : Session::TYPE_INDIVIDUAL)
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->dehydrated()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('group_id', null);
                    $set('customer_id', null);
                    $set('course_id', null);
                    $set('teacher_id', null);
                    $set('identifier', null);
                })
                ->required(),
            Forms\Components\Select::make('group_id')
                ->relationship('group', 'title')
                ->visible(fn (Get $get): bool => $get('session_type') === Session::TYPE_GROUP)
                ->required(fn (Get $get): bool => $get('session_type') === Session::TYPE_GROUP)
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    $group = $get('group_id') ? Group::find($get('group_id')) : null;
                    $set('course_id', $group?->course_id);
                    $set('teacher_id', $group?->teacher_id);
                    static::updateGroupIdentifier($get, $set);
                }),
            Forms\Components\Select::make('customer_id')
                ->relationship('customer', 'name')
                ->visible(fn (Get $get): bool => $get('session_type') === Session::TYPE_INDIVIDUAL)
                ->required(fn (Get $get): bool => $get('session_type') === Session::TYPE_INDIVIDUAL),
            Forms\Components\Select::make('course_id')
                ->relationship('course', 'title')
                ->live()
                ->disabled(fn (Get $get): bool => $get('session_type') === Session::TYPE_GROUP)
                ->dehydrated()
                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateGroupIdentifier($get, $set))
                ->required(),
            Forms\Components\Select::make('teacher_id')->relationship('teacher', 'full_name')
                ->disabled(fn (Get $get): bool => $get('session_type') === Session::TYPE_GROUP)
                ->dehydrated()
                ->required(),
            Forms\Components\DateTimePicker::make('starts_at')
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateGroupIdentifier($get, $set))
                ->required(),
            Forms\Components\DateTimePicker::make('ends_at')->afterOrEqual('starts_at')->required(),
            Forms\Components\Select::make('status')
                ->options(fn (): array => app(SessionLifecycleService::class)->statuses())
                ->default('scheduled')
                ->required(),
            Forms\Components\TextInput::make('identifier')
                ->required(fn (Get $get): bool => $get('session_type') === Session::TYPE_INDIVIDUAL)
                ->disabled(fn (Get $get): bool => $get('session_type') === Session::TYPE_GROUP)
                ->dehydrated()
                ->maxLength(255),
            Forms\Components\Select::make('attendance_status')
                ->options(['present' => 'Present', 'absent' => 'Absent', 'excused' => 'Excused'])
                ->visible(fn (Get $get): bool => $get('session_type') === Session::TYPE_INDIVIDUAL),
        ]);
    }

    /** @return array<string, string> */
    private static function sessionTypeOptions(string $operation): array
    {
        $options = [];
        $canUseUnifiedPermissions = hexa()->can('session.create') || hexa()->can('session.update');

        if ($canUseUnifiedPermissions || hexa()->can('group_session.create') || ($operation === 'edit' && hexa()->can('group_session.update'))) {
            $options[Session::TYPE_GROUP] = 'Group session';
        }

        if ($canUseUnifiedPermissions || hexa()->can('individual_session.create') || ($operation === 'edit' && hexa()->can('individual_session.update'))) {
            $options[Session::TYPE_INDIVIDUAL] = 'Individual session';
        }

        return $options;
    }

    private static function updateGroupIdentifier(Get $get, Set $set): void
    {
        if ($get('session_type') !== Session::TYPE_GROUP) {
            return;
        }

        $course = $get('course_id') ? Course::find($get('course_id')) : null;
        $group = $get('group_id') ? Group::find($get('group_id')) : null;
        $start = $get('starts_at');

        if ($course && $group && $start) {
            $set('identifier', $course->title.' - '.$group->title.' - '.Carbon::parse($start)->format('d/m/Y H:i'));
        }
    }
}
