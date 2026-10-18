<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Services\StudentsDashboardData;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class StudentsDashboard extends Page
{
    protected static ?int $navigationSort = 0;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected string $view = 'filament.clusters.academic.pages.students-dashboard';

    protected static ?string $cluster = AcademicCluster::class;

    use HasHexaLite;

    public string $period = '30';

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $period = in_array($this->period, ['7', '30', '90'], true) ? $this->period : '30';

        return app(StudentsDashboardData::class)->summary(
            canViewStudents: hexa()->can('student.index'),
            canCreateStudents: hexa()->can('student.create'),
            canViewTasks: hexa()->can('task.index'),
            canViewAllTasks: hexa()->can('task.view_all'),
            period: $period,
        ) + [
            'students_url' => hexa()->can('student.index') ? StudentResource::getUrl('index') : null,
            'create_student_url' => hexa()->can('student.create') ? StudentResource::getUrl('create') : null,
            'tasks_url' => hexa()->can('task.index') ? TaskResource::getUrl('index') : null,
        ];
    }

    public function defineGates(): array
    {
        return [
            'students-dashboard.view' => __('Allows viewing the students dashboard'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('students-dashboard.view');
    }
}
