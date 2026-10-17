<?php

namespace App\Filament\Resources\IndividualSessions\Actions;

use App\Filament\Resources\GroupSessions\Actions\InputSessionAttendanceAction;
use App\Models\Employee;
use App\Models\IndividualSession;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class InputIndividualSessionAttendanceAction
{
    public static function make(): Action
    {
        return Action::make('inputSessionAttendance')
            ->label('Input session attendance')
            ->icon('heroicon-o-clipboard-document-check')
            ->visible(fn (IndividualSession $record): bool => static::canManage($record))
            ->modalHeading(fn (IndividualSession $record): string => 'Attendance: '.$record->identifier)
            ->modalSubmitActionLabel('Save attendance')
            ->fillForm(fn (IndividualSession $record): array => ['customer_name' => $record->customer?->name ?? 'Unknown customer', 'attendance_status' => $record->attendance_status])
            ->schema([
                TextInput::make('customer_name')->label('Participant')->disabled()->dehydrated(),
                Select::make('attendance_status')->label('Attendance Status')->options(InputSessionAttendanceAction::statuses())->required(),
            ])
            ->action(function (IndividualSession $record, array $data): void {
                abort_unless(static::canManage($record), 403);
                $status = $data['attendance_status'] ?? null;
                if (! array_key_exists($status, InputSessionAttendanceAction::statuses())) {
                    abort(422, 'Choose a valid attendance status.');
                }

                $record->forceFill(['attendance_status' => $status])->save();
                Notification::make()->title('Attendance saved')->success()->send();
            });
    }

    public static function canManage(IndividualSession $session): bool
    {
        if (! auth()->check() || ! hexa()->can('individual_session.update')) {
            return false;
        }

        $employee = Employee::query()->where('user_id', auth()->id())->first();

        return $employee !== null && (int) $session->getAttribute('teacher') === (int) $employee->getKey();
    }
}
