<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Operations\OperationsCluster;
use Filament\Pages\Page;
use UnitEnum;

class TasksAndProjects extends Page
{
    protected static ?int $navigationSort = 2;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.projects.pages.tasks-and-projects';
    protected static ?string $cluster = OperationsCluster::class;
}
