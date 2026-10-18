<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Academic\AcademicCluster;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Sessions\SessionResource;
use App\Services\TeachersDashboardData;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class TeachersDashboard extends Page
{
    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected string $view = 'filament.clusters.academic.pages.teachers-dashboard';

    protected static ?string $cluster = AcademicCluster::class;

    use HasHexaLite;

    public string $period = '30';

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $period = in_array($this->period, ['7', '30', '90'], true) ? $this->period : '30';
        $permissions = [
            'employee.index' => hexa()->can('employee.index'),
            'employee.create' => hexa()->can('employee.create'),
            'group.index' => hexa()->can('group.index'),
            'session.index' => hexa()->can('session.index'),
            'group_session.index' => hexa()->can('group_session.index'),
            'individual_session.index' => hexa()->can('individual_session.index'),
        ];
        $canViewSessions = $permissions['session.index']
            || ($permissions['group_session.index'] && ! $permissions['individual_session.index'])
            || ($permissions['individual_session.index'] && ! $permissions['group_session.index']);

        return app(TeachersDashboardData::class)->summary($permissions, period: $period) + [
            'employees_url' => $permissions['employee.index'] ? EmployeeResource::getUrl('index') : null,
            'create_employee_url' => $permissions['employee.create'] ? EmployeeResource::getUrl('create') : null,
            'groups_url' => $permissions['group.index'] ? GroupResource::getUrl('index') : null,
            'sessions_url' => $canViewSessions ? SessionResource::getUrl('index') : null,
        ];
    }

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
