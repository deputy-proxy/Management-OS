<?php
namespace App\Filament\Resources\Tasks\Actions;

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
                            ->visible(fn () => $record !== null && $record->tasks()->exists())
                            ->schema([
                                View::make('filament.tasks.task-list')
                                    ->viewData([
                                        'tasks' => $record !== null ? $record->tasks()->with('assignedTo')->get() : collect(),
                                    ]),
                            ]),
                        Tabs\Tab::make('Add Task')
                            ->icon('heroicon-m-plus-circle')
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
                                    ->options(fn () => User::pluck('name', 'id'))
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
                if (! $record) {
                    return;
                }
                $record->tasks()->create($data);
            })
            ->badge(function ($record): ?int {
                if (! $record) {
                    return null;
                }
                if (! in_array(HasTasks::class, class_uses_recursive($record))) {
                    return null;
                }
                $count = $record->tasks()->count();
                return $count > 0 ? $count : null;
            })
            ->badgeColor('gray')
            ->successNotificationTitle('Task created successfully.');
    }

    public static function getDefaultName(): ?string
    {
        return 'tasks';
    }
}