<?php

namespace App\Filament\Clusters\Academic\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use Filament\Pages\Page;

class StudentsDashboard extends Page
{
    protected string $view = 'filament.clusters.academic.pages.students-dashboard';

    protected static ?string $cluster = AcademicCluster::class;
}
