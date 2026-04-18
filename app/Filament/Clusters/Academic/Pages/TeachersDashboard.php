<?php

namespace App\Filament\Clusters\Academic\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use Filament\Pages\Page;
use UnitEnum;

class TeachersDashboard extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'All';
    protected string $view = 'filament.clusters.academic.pages.teachers-dashboard';
    protected static ?string $cluster = AcademicCluster::class;
}
