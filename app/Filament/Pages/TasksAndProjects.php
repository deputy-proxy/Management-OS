<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Operations\OperationsCluster;
use App\Filament\Resources\ProjectPhases\ProjectPhaseResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Services\TasksAndProjectsData;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class TasksAndProjects extends Page
{
    use HasHexaLite;

    protected static ?int $navigationSort = 0;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected string $view = 'filament.clusters.projects.pages.tasks-and-projects';

    protected static ?string $cluster = OperationsCluster::class;

    public ?string $taskStatusFilter = null;

    public ?string $taskOwnerFilter = null;

    public ?string $taskProjectFilter = null;

    public string $dueDateFilter = 'all';

    public function defineGates(): array
    {
        return [
            'tasks-and-projects.view' => __('Allows viewing the tasks and projects'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('tasks-and-projects.view');
    }

    /**
     * @return array<string, string|null>
     */
    public function getAuthorizedResourceUrls(): array
    {
        return [
            'tasks' => hexa()->can('task.index') ? TaskResource::getUrl('index') : null,
            'projects' => hexa()->can('project.index') ? ProjectResource::getUrl('index') : null,
            'phases' => hexa()->can('project_phase.index') ? ProjectPhaseResource::getUrl('index') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardSummary(): array
    {
        $canViewTasks = hexa()->can('task.index');

        return app(TasksAndProjectsData::class)->summary(
            canViewTasks: $canViewTasks,
            canViewAllTasks: $canViewTasks && hexa()->can('task.view_all'),
            canViewProjects: hexa()->can('project.index'),
            canViewPhases: hexa()->can('project_phase.index'),
            statusFilter: $this->taskStatusFilter,
            ownerFilter: $this->taskOwnerFilter !== null && $this->taskOwnerFilter !== '' ? (int) $this->taskOwnerFilter : null,
            projectFilter: $this->taskProjectFilter !== null && $this->taskProjectFilter !== '' ? (int) $this->taskProjectFilter : null,
            dueDateFilter: $this->dueDateFilter,
        );
    }
}
