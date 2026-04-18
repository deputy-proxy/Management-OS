<?php

namespace App\Filament\Clusters\Academic\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use Filament\Pages\Page;
use UnitEnum;

class TeachersDashboard extends Page
{
    protected static ?int $navigationSort = 1;
    protected static string|UnitEnum|null $navigationGroup = 'Dashboards';
    protected string $view = 'filament.clusters.academic.pages.teachers-dashboard';
    protected static ?string $cluster = AcademicCluster::class;
}
