<?php
namespace App\Filament\Resources\Tasks\Actions;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Concerns\HasTasks;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\View;

class TasksAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->visible(fn (?Model $record): bool => static::canUse($record))
            ->authorize(fn (?Model $record): bool => static::canUse($record))
            ->label(__('Tasks'))
            ->icon('heroicon-m-check-circle')
            ->slideOver()
            ->modalHeading(__('Tasks'))
            ->color('primary')
            ->schema(fn (Schema $schema, ?Model $record) => $schema->components([
                Tabs::make()
                    ->tabs([
                        Tabs\Tab::make('Current Tasks')
                            ->icon('heroicon-m-check-circle')
                            ->visible(fn () => $record !== null && hexa()->can('task.index') && $record->tasks()->exists())
                            ->schema([
                                View::make('filament.tasks.task-list')
                                    ->viewData([
                                        'tasks' => $record !== null && hexa()->can('task.index')
                                            ? TaskResource::getEloquentQuery()
                                                ->whereIn('tasks.id', $record->tasks()->select('tasks.id'))
                                                ->with('assignedTo')
                                                ->get()
                                            : collect(),
                                    ]),
                            ]),
                        Tabs\Tab::make('Add Task')
                            ->icon('heroicon-m-plus-circle')
                            ->visible(fn (): bool => hexa()->can('task.create'))
                            ->schema([
                                Forms\Components\TextInput::make('taskable_type')
                                    ->label('Taskable Type')
                                    ->hidden()
                                    ->disabled(),
                                Forms\Components\TextInput::make('taskable_id')
                                    ->label('Taskable ID')
                                    ->hidden()
                                    ->disabled(),
                                Forms\Components\TextInput::make('action')
                                    ->required(),
                                Forms\Components\Textarea::make('description')
                                    ->required(),
                                Forms\Components\Select::make('assigned_to')
                                    ->options(fn () => hexa()->can('task.assign')
                                        ? User::query()->pluck('name', 'id')
                                        : User::query()->whereKey(auth()->id())->pluck('name', 'id'))
                                    ->default(fn () => auth()->id())
                                    ->required(),
                                Forms\Components\Select::make('project_id')
                                    ->options(fn () => Project::pluck('title', 'id'))
                                    ->required(),
                                Forms\Components\Select::make('project_phase_id')
                                    ->options(fn () => ProjectPhase::pluck('title', 'id'))
                                    ->required(),
                                Forms\Components\TextInput::make('status')
                                    ->required(),
                                Forms\Components\DatePicker::make('due_date')
                                    ->required(),
                            ]),
                    ])
                    ->persistTabInQueryString(false)
                    ->columnSpanFull(),
            ]))
            ->mountUsing(function (Schema $schema, ?Model $record): void {
                if (! $record) {
                    return;
                }
                $schema->fill([
                    'taskable_id'   => $record->id,
                    'taskable_type' => get_class($record),
                ]);
            })
            ->action(function (array $data, ?Model $record): void {
                abort_unless(static::canUse($record) && hexa()->can('task.create'), 403);

                if (! $record) {
                    return;
                }

                $assignedTo = hexa()->can('task.assign')
                    ? (int) $data['assigned_to']
                    : (int) auth()->id();

                $record->tasks()->create([
                    'action' => $data['action'],
                    'description' => $data['description'],
                    'assigned_to' => $assignedTo,
                    'project_id' => $data['project_id'],
                    'project_phase_id' => $data['project_phase_id'],
                    'status' => $data['status'],
                    'due_date' => $data['due_date'],
                ]);
            })
            ->badge(function ($record): ?int {
                if (! $record || ! hexa()->can('task.index') || ! in_array(HasTasks::class, class_uses_recursive($record), true)) {
                    return null;
                }

                $count = TaskResource::getEloquentQuery()
                    ->whereIn('tasks.id', $record->tasks()->select('tasks.id'))
                    ->count();

                return $count > 0 ? $count : null;
            })
            ->badgeColor('gray')
            ->successNotificationTitle('Task created successfully.');
    }


    public static function canUse(?Model $record): bool
    {
        return $record !== null
            && in_array(HasTasks::class, class_uses_recursive($record), true)
            && (hexa()->can('task.index') || hexa()->can('task.create'));
    }

    public static function getDefaultName(): ?string
    {
        return 'tasks';
    }
}