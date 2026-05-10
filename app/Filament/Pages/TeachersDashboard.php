<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use Filament\Pages\Page;
use UnitEnum;
use Hexters\HexaLite\HasHexaLite;

class TeachersDashboard extends Page
{
    protected static ?int $navigationSort = 1;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.academic.pages.teachers-dashboard';
    protected static ?string $cluster = AcademicCluster::class;
    use HasHexaLite;

    public function defineGates(): array
    {
        return [
            'teachers-dashboard.view' => __('Allows viewing the teachers dashboard'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('teachers-dashboard.view');
    }
}
