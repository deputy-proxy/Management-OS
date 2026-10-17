<?php

namespace App\Filament\Resources\GroupSessions\Actions;

use App\Models\Employee;
use App\Models\GroupSession;
use App\Models\SessionAttendance;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProvideSessionFeedbackAction
{
    /** @return array<string, string> */
    public static function achievementBadges(): array
    {
        return [
            'excellent' => 'Excellent',
            'good' => 'Good',
            'developing' => 'Developing',
            'needs_support' => 'Needs support',
        ];
    }

    public static function make(): Action
    {
        return Action::make('provideSessionFeedback')
            ->label('Provide post-session feedback')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->visible(fn (GroupSession $record): bool => static::canManage($record))
            ->modalHeading(fn (GroupSession $record): string => 'Post-session feedback: '.$record->identifier)
            ->modalSubmitActionLabel('Save feedback')
            ->fillForm(fn (GroupSession $record): array => [
                'students' => $record->sessionAttendances()
                    ->with('student')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (SessionAttendance $attendance): array => [
                        'attendance_id' => $attendance->getKey(),
                        'student_name' => $attendance->student?->full_name ?? 'Unknown student',
                        'achievement_badge' => $attendance->achievement_badge,
                        'feedback' => $attendance->feedback,
                    ])
                    ->all(),
            ])
            ->schema([
                Repeater::make('students')
                    ->label('Students in this session')
                    ->table([
                        Repeater\TableColumn::make('Student Full Name'),
                        Repeater\TableColumn::make('Achievement Badge'),
                        Repeater\TableColumn::make('Update/Feedback'),
                    ])
                    ->schema([
                        Hidden::make('attendance_id')->required(),
                        TextInput::make('student_name')
                            ->label('Student Full Name')
                            ->disabled()
                            ->dehydrated(),
                        Select::make('achievement_badge')
                            ->label('Achievement Badge')
                            ->options(static::achievementBadges())
                            ->required(),
                        Textarea::make('feedback')
                            ->label('Update/Feedback')
                            ->rows(2)
                            ->maxLength(5000),
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull(),
            ])
            ->action(function (GroupSession $record, array $data): void {
                abort_unless(static::canManage($record), 403);

                $rows = collect($data['students'] ?? []);
                $attendanceIds = $rows->pluck('attendance_id')->map(fn ($id): int => (int) $id);
                $actualIds = $record->sessionAttendances()->whereIn('id', $attendanceIds)->pluck('id')->map(fn ($id): int => (int) $id);

                if ($attendanceIds->count() !== $actualIds->count() || $attendanceIds->unique()->count() !== $attendanceIds->count()) {
                    throw ValidationException::withMessages([
                        'students' => 'The submitted rows do not match the students enrolled in this session. Refresh and try again.',
                    ]);
                }

                DB::transaction(function () use ($rows, $record): void {
                    foreach ($rows as $row) {
                        $badge = $row['achievement_badge'] ?? null;
                        if (! array_key_exists($badge, static::achievementBadges())) {
                            throw ValidationException::withMessages([
                                'students' => 'Choose a valid achievement badge for every student.',
                            ]);
                        }

                        $record->sessionAttendances()
                            ->whereKey($row['attendance_id'])
                            ->update([
                                'achievement_badge' => $badge,
                                'feedback' => trim((string) ($row['feedback'] ?? '')) ?: null,
                            ]);
                    }
                });

                Notification::make()
                    ->title('Session feedback saved')
                    ->success()
                    ->send();
            });
    }

    public static function canManage(GroupSession $session): bool
    {
        if (! auth()->check() || ! hexa()->can('group_session.update')) {
            return false;
        }

        $employee = Employee::query()->where('user_id', auth()->id())->first();

        return $employee !== null
            && (int) $session->teacher_id === (int) $employee->getKey()
            && (int) $session->group?->teacher_id === (int) $employee->getKey();
    }
}
