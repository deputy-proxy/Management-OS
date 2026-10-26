<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Models\Task;
use App\Models\User;
use App\Rules\TaskDependenciesAreAcyclic;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();
        $canAssign = hexa()->can('task.assign');
        $canViewAll = hexa()->can('task.view_all');

        return $schema->components([

            TextInput::make('action')
                ->label('Task Title')
                ->required(),

            Textarea::make('description')
                ->required()
                ->columnSpanFull(),

            // ── Assigned To ──────────────────────────────────────────────
            // Roles WITH task.assign: full searchable select of users
            Select::make('assigned_to')
                ->label('Assigned To')
                ->options(function () use ($canViewAll) {
                    // "All" roles (Admin/Owner/Director) can assign to anyone
                    if ($canViewAll) {
                        return User::orderBy('name')->pluck('name', 'id');
                    }

                    // AcademicDirector has task.assign but NOT task.view_all
                    return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Academician', 'Teacher', 'Marketer', 'Coordinator'])
                    )->orderBy('name')->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->visible($canAssign),

            // Roles WITHOUT task.assign: locked to themselves, value forced server-side
            Select::make('assigned_to')
                ->label('Assigned To')
                ->options([$user->id => $user->name])
                ->default($user->id)
                ->disabled()   // renders as read-only
                ->dehydrated() // still submits the value
                ->visible(! $canAssign),

            // ── Hidden fields ─────────────────────────────────────────────
            Hidden::make('created_by')
                ->default($user->id),

            // ── Other fields ──────────────────────────────────────────────
            Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'in_progress' => 'In Progress',
                    'done' => 'Done',
                ])
                ->default('pending')
                ->required(),

            DatePicker::make('due_date')
                ->required(),

            TextInput::make('estimated_effort_minutes')
                ->label('Estimated effort (minutes)')
                ->helperText('Optional estimate for workload planning. This is not a capacity or utilization calculation.')
                ->rules(['nullable'])
                ->numeric()
                ->integer()
                ->minValue(1),

            Select::make('project_id')
                ->label('Project')
                ->relationship('project', 'title')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('project_phase_id')
                ->label('Project Phase')
                ->relationship('projectPhase', 'title')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('dependencies')
                ->label('Depends on tasks')
                ->helperText('Select prerequisite tasks. A task cannot depend on itself or on a task chain that leads back to it.')
                ->relationship('dependencies', 'action', modifyQueryUsing: fn (Builder $query) => hexa()->can('task.view_all')
                    ? $query
                    : $query->where(fn (Builder $scope) => $scope->where('assigned_to', auth()->id())->orWhere('created_by', auth()->id())))
                ->multiple()
                ->searchable()
                ->rules(fn (?Task $record): array => [new TaskDependenciesAreAcyclic($record?->getKey())]),
        ]);
    }
}
