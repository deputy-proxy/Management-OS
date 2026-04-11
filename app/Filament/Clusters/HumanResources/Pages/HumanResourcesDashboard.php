<?php

namespace App\Filament\Clusters\HumanResources\Pages;

use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
use Filament\Pages\Page;


class HumanResourcesDashboard extends Page
{
    protected string $view = 'filament.clusters.human-resources.pages.human-resources-dashboard';

    protected static ?string $cluster = HumanResourcesCluster::class;
    protected static ?int $navigationSort = 1;


}
