<?php

namespace App\Filament\Clusters\General\Pages;

use App\Filament\Clusters\General\GeneralCluster;
use Filament\Pages\Page;
use UnitEnum;


class GeneralDashboard extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.human-resources.pages.human-resources-dashboard';
    protected static ?string $cluster = GeneralCluster::class;
    protected static ?int $navigationSort = 14;


}
