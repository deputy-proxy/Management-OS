<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use Filament\Pages\Page;
use UnitEnum;

class TeachersDashboard extends Page
{
    protected static ?int $navigationSort = 14;
    protected static string|UnitEnum|null $navigationGroup = 'Dashboards';
    protected string $view = 'filament.clusters.academic.pages.teachers-dashboard';
    protected static ?string $cluster = AcademicCluster::class;
}
