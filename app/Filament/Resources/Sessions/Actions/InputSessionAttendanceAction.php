<?php

namespace App\Filament\Resources\Sessions\Actions;

use App\Models\Employee;
use App\Models\Session;
use App\Models\SessionAttendance;
use App\Services\Academic\SessionRosterService;
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
            ->visible(fn (Session $record): bool => static::canManage($record))
            ->modalHeading(fn (Session $record): string => 'Attendance: '.$record->identifier)
            ->modalSubmitActionLabel('Save attendance')
            ->fillForm(function (Session $record): array {
                if ($record->session_type === Session::TYPE_INDIVIDUAL) {
                    return ['customer_name' => $record->customer?->name ?? 'Unknown customer', 'attendance_status' => $record->attendance_status];
                }

                $roster = app(SessionRosterService::class);
                $roster->synchronize($record);

                return ['students' => $roster->eligibleAttendances($record)->with('student')->orderBy('student_id')->get()
                    ->map(fn (SessionAttendance $attendance): array => [
                        'attendance_id' => $attendance->getKey(),
                        'student_name' => $attendance->student?->full_name ?? 'Unknown student',
                        'status' => $attendance->status,
                    ])->all()];
            })
            ->schema(fn (Session $record): array => $record->session_type === Session::TYPE_INDIVIDUAL ? [
                TextInput::make('customer_name')->label('Participant')->disabled()->dehydrated(),
                Select::make('attendance_status')->label('Attendance Status')->options(static::statuses())->required(),
            ] : [
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
            ->action(function (Session $record, array $data): void {
                abort_unless(static::canManage($record), 403);
                if ($record->session_type === Session::TYPE_INDIVIDUAL) {
                    $status = $data['attendance_status'] ?? null;
                    if (! array_key_exists($status, static::statuses())) {
                        throw ValidationException::withMessages(['attendance_status' => 'Choose a valid attendance status.']);
                    }
                    $record->forceFill(['attendance_status' => $status])->save();
                    Notification::make()->title('Attendance saved')->success()->send();

                    return;
                }
                $roster = app(SessionRosterService::class);
                $roster->synchronize($record);
                $eligibleStudentIds = $roster->eligibleStudentIds($record);
                $rows = collect($data['students'] ?? []);
                $ids = $rows->pluck('attendance_id')->map(fn ($id): int => (int) $id);
                $actualIds = $roster->eligibleAttendances($record)->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id);
                $submittedStudentIds = $record->sessionAttendances()->whereIn('id', $ids)->pluck('student_id')->map(fn ($id): int => (int) $id);

                if ($submittedStudentIds->contains(fn (int $id): bool => ! in_array($id, $eligibleStudentIds, true))) {
                    throw ValidationException::withMessages(['students' => 'Attendance can only be recorded for students who were members of this group on the session date.']);
                }

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

    public static function canManage(Session $session): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $employee = Employee::query()->where('user_id', auth()->id())->first();
        if ($employee === null || (int) $session->teacher_id !== (int) $employee->getKey()) {
            return false;
        }

        if ($session->session_type === Session::TYPE_INDIVIDUAL) {
            return hexa()->can('session.update') || hexa()->can('individual_session.update');
        }

        return (hexa()->can('session.update') || hexa()->can('group_session.update'))
            && (int) $session->group?->teacher_id === (int) $employee->getKey();
    }
}
