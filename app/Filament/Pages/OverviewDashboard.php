<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class OverviewDashboard extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.overview-dashboard';

    use HasHexaLite;

    public function defineGates(): array
    {
        return [
            'overview-dashboard.view' => __('Allows viewing the overview dashboard'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('overview-dashboard.view');
    }

    /**
     * Overview links are metadata-only. Each destination is checked against its
     * own page permission before a URL is exposed to the view.
     *
     * @return array<int, array{title: string, description: string, page: class-string<Page>|null, owner: string, scope: string}>
     */
    public function getOverviewItems(): array
    {
        return [
            [
                'title' => 'General Overview',
                'description' => 'Employee presence, course margin, feedback, and the weekly schedule.',
                'page' => GeneralDashboard::class,
                'owner' => 'Operations',
                'scope' => 'Available',
            ],
            [
                'title' => 'Financial Overview',
                'description' => 'Revenue, costs, margin, receivables, payables, and period comparisons.',
                'page' => FinanceDashboard::class,
                'owner' => 'Finance',
                'scope' => 'Available',
            ],
            [
                'title' => 'Customer Dashboard',
                'description' => 'Customer and lead status, recent activity, and authorized follow-up links.',
                'page' => CustomerDashboard::class,
                'owner' => 'Customer Management',
                'scope' => 'Available',
            ],
            [
                'title' => 'Tasks & Projects',
                'description' => 'Active work, project phases, ownership, due dates, and overdue tasks.',
                'page' => TasksAndProjects::class,
                'owner' => 'Project Management',
                'scope' => 'Available',
            ],
            [
                'title' => 'Products Overview',
                'description' => 'Product catalog, productable records, prices, availability, and lifecycle state.',
                'page' => ProductsOverview::class,
                'owner' => 'Product Management',
                'scope' => 'Available',
            ],
        ];
    }

    /**
     * @param  class-string<Page>|null  $page
     */
    public function canOpenOverview(?string $page): bool
    {
        return $page !== null && is_subclass_of($page, Page::class) && $page::canAccess();
    }
}