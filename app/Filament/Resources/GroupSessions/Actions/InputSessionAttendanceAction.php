<?php

namespace App\Filament\Resources\GroupSessions\Actions;

use App\Models\Employee;
use App\Models\GroupSession;
use App\Models\SessionAttendance;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InputSessionAttendanceAction
{
    /** @return array<string, string> */
    public static function statuses(): array
    {
        return ['present' => 'Present', 'absent' => 'Absent', 'excused' => 'Excused'];
    }

    public static function make(): Action
    {
        return Action::make('inputSessionAttendance')
            ->label('Input session attendance')
            ->icon('heroicon-o-clipboard-document-check')
            ->visible(fn (GroupSession $record): bool => static::canManage($record))
            ->modalHeading(fn (GroupSession $record): string => 'Attendance: '.$record->identifier)
            ->modalSubmitActionLabel('Save attendance')
            ->fillForm(fn (GroupSession $record): array => [
                'students' => $record->sessionAttendances()->with('student')->orderBy('student_id')->get()
                    ->map(fn (SessionAttendance $attendance): array => [
                        'attendance_id' => $attendance->getKey(),
                        'student_name' => $attendance->student?->full_name ?? 'Unknown student',
                        'status' => $attendance->status,
                    ])->all(),
            ])
            ->schema([
                Repeater::make('students')
                    ->label('Students enrolled in this session')
                    ->table([
                        Repeater\TableColumn::make('Student Full Name'),
                        Repeater\TableColumn::make('Attendance Status'),
                    ])
                    ->schema([
                        Hidden::make('attendance_id')->required(),
                        TextInput::make('student_name')->label('Student Full Name')->disabled()->dehydrated(),
                        Select::make('status')->label('Attendance Status')->options(static::statuses())->required(),
                    ])
                    ->addable(false)->deletable(false)->reorderable(false)->columnSpanFull(),
            ])
            ->action(function (GroupSession $record, array $data): void {
                abort_unless(static::canManage($record), 403);
                $rows = collect($data['students'] ?? []);
                $ids = $rows->pluck('attendance_id')->map(fn ($id): int => (int) $id);
                $actualIds = $record->sessionAttendances()->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id);

                if ($ids->count() !== $actualIds->count() || $ids->unique()->count() !== $ids->count()) {
                    throw ValidationException::withMessages(['students' => 'Attendance rows must belong to this session. Refresh and try again.']);
                }

                DB::transaction(function () use ($rows, $record): void {
                    foreach ($rows as $row) {
                        $status = $row['status'] ?? null;
                        if (! array_key_exists($status, static::statuses())) {
                            throw ValidationException::withMessages(['students' => 'Choose a valid attendance status for every student.']);
                        }

                        $record->sessionAttendances()->whereKey($row['attendance_id'])->update(['status' => $status]);
                    }
                });

                Notification::make()->title('Attendance saved')->success()->send();
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
