<?php

namespace App\Filament\Clusters\Academic\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use Filament\Pages\Page;
use UnitEnum;

class StudentsDashboard extends Page
{
    protected static ?int $navigationSort = 13;
    protected static string|UnitEnum|null $navigationGroup = 'Dashboards';
    protected string $view = 'filament.clusters.academic.pages.students-dashboard';
    protected static ?string $cluster = AcademicCluster::class;
}
