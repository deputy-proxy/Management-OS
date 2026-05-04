<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use Filament\Pages\Page;
use UnitEnum;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class StudentsDashboard extends Page
{
    protected static ?int $navigationSort = 0;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;
    protected string $view = 'filament.clusters.academic.pages.students-dashboard';
    protected static ?string $cluster = AcademicCluster::class;
}
