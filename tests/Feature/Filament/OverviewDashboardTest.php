<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\CustomerDashboard;
use App\Filament\Pages\FinanceDashboard;
use App\Filament\Pages\GeneralDashboard;
use App\Filament\Pages\OverviewDashboard;
use App\Filament\Pages\TasksAndProjects;
use Filament\Pages\Page;
use Tests\TestCase;

class OverviewDashboardTest extends TestCase
{
    public function test_overview_catalog_documents_all_child_overviews(): void
    {
        $items = app(OverviewDashboard::class)->getOverviewItems();

        $this->assertSame(
            ['General Overview', 'Financial Overview', 'Customer Dashboard', 'Tasks & Projects'],
            array_column($items, 'title'),
        );

        $this->assertSame(
            ['Operations', 'Finance', 'Customer Management', 'Project Management'],
            array_column($items, 'owner'),
        );

        $this->assertCount(4, $items);
        $this->assertSame('Available', $items[2]['scope']);
    }

    public function test_overview_links_only_reference_existing_filament_pages(): void
    {
        $items = app(OverviewDashboard::class)->getOverviewItems();

        $this->assertSame(GeneralDashboard::class, $items[0]['page']);
        $this->assertSame(FinanceDashboard::class, $items[1]['page']);
        $this->assertSame(TasksAndProjects::class, $items[3]['page']);

        foreach ($items as $item) {
            if ($item['page'] !== null) {
                $this->assertTrue(is_subclass_of($item['page'], Page::class));
            }
        }

        $this->assertSame(CustomerDashboard::class, $items[2]['page']);
    }

    public function test_customer_dashboard_declares_a_dedicated_access_permission(): void
    {
        $this->assertSame(
            ['customer-dashboard.view' => __('Allows viewing the customer dashboard')],
            (new CustomerDashboard)->defineGates(),
        );
    }

    public function test_tasks_and_projects_page_declares_a_dedicated_access_permission(): void
    {
        $this->assertSame(
            ['tasks-and-projects.view' => __('Allows viewing the tasks and projects')],
            (new TasksAndProjects)->defineGates(),
        );
    }

    public function test_page_declares_a_dedicated_access_permission(): void
    {
        $this->assertSame(
            ['overview-dashboard.view' => __('Allows viewing the overview dashboard')],
            (new OverviewDashboard)->defineGates(),
        );
    }
}
