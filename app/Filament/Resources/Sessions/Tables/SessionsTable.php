<?php

namespace App\Filament\Resources\Sessions\Tables;

use App\Filament\Resources\EmployeePaySlips\Actions\AddToPaySlipAction;
use App\Filament\Resources\Sessions\Actions\InputSessionAttendanceAction;
use App\Filament\Resources\Sessions\Actions\ProvideSessionFeedbackAction;
use App\Filament\Resources\Tasks\Actions\TasksAction;
use App\Models\Session;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class SessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('session_type')->label('Type')->formatStateUsing(fn (string $state): string => $state === Session::TYPE_GROUP ? 'Group' : 'Individual')->sortable(),
            Tables\Columns\TextColumn::make('identifier')->searchable(),
            Tables\Columns\TextColumn::make('group.title')->label('Group')->placeholder('—'),
            Tables\Columns\TextColumn::make('customer.name')->label('Customer')->placeholder('—'),
            Tables\Columns\TextColumn::make('course.title')->label('Course')->sortable(),
            Tables\Columns\TextColumn::make('teacher.full_name')->label('Teacher'),
            Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('ends_at')->dateTime(),
            Tables\Columns\TextColumn::make('status'),
            Tables\Columns\TextColumn::make('attendance_status')->label('Attendance')->placeholder('—'),
        ])->filters([Tables\Filters\TrashedFilter::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->disabled(fn (Session $record): bool => $record->sessionAttendances()->exists() || $record->employeeTimesheets()->exists()),
                TasksAction::make(),
                InputSessionAttendanceAction::make(),
                ProvideSessionFeedbackAction::make()->visible(fn (Session $record): bool => $record->session_type === Session::TYPE_GROUP && ProvideSessionFeedbackAction::canManage($record)),
                AddToPaySlipAction::make(),
                CommentsAction::make(),
            ])->toolbarActions([BulkActionGroup::make([RestoreBulkAction::make()])]);
    }
}
