<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Operations\OperationsCluster;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class TasksAndProjects extends Page
{
    protected static ?int $navigationSort = 0;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.projects.pages.tasks-and-projects';
    protected static ?string $cluster = OperationsCluster::class;
    use HasHexaLite;
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
}
