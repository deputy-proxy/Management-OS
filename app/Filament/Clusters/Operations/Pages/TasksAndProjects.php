<?php

namespace App\Filament\Clusters\Operations\Pages;

use App\Filament\Clusters\Operations\OperationsCluster;
use Filament\Pages\Page;

class TasksAndProjects extends Page
{
    protected string $view = 'filament.clusters.projects.pages.tasks-and-projects';

    protected static ?string $cluster = OperationsCluster::class;
}
