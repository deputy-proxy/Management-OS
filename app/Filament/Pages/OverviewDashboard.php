<?php

namespace App\\Filament\\Pages;

use App\\Filament\\Resources\\Articles\\ArticleResource;
use App\\Filament\\Resources\\CompetitorOffers\\CompetitorOfferResource;
use App\\Filament\\Resources\\CustomerLeads\\CustomerLeadResource;
use App\\Filament\\Resources\\Customers\\CustomerResource;
use App\\Filament\\Resources\\Employees\\EmployeeResource;
use App\\Filament\\Resources\\Newsletters\\NewsletterResource;
use App\\Filament\\Resources\\PaidCampaigns\\PaidCampaignResource;
use App\\Filament\\Resources\\Projects\\ProjectResource;
use App\\Filament\\Resources\\Students\\StudentResource;
use App\\Filament\\Resources\\Tasks\\TaskResource;
use App\\Services\\AdminOverviewDashboardData;
use Filament\\Pages\\Dashboard as BaseDashboard;
use Filament\\Pages\\Page;
use Hexters\\HexaLite\\HasHexaLite;

class OverviewDashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.overview-dashboard';

    use HasHexaLite;

    public function defineGates(): array
    {
        return [
            'overview-dashboard.view' => __('Allows viewing the overview dashboard'),
        ];
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
        ];
    }

    /**
     * Return only metrics and activity for resources the current viewer may access.
     * Dashboard access itself never grants access to an underlying resource.
     *
     * @return array<string, mixed>
     */
    public function getDashboardSummary(): array
    {
        $resources = [
            'student.index' => StudentResource::class,
            'employee.index' => EmployeeResource::class,
            'customer.index' => CustomerResource::class,
            'customer_lead.index' => CustomerLeadResource::class,
            'competitor_offer.index' => CompetitorOfferResource::class,
            'project.index' => ProjectResource::class,
            'task.index' => TaskResource::class,
            'article.index' => ArticleResource::class,
            'newsletter.index' => NewsletterResource::class,
            'paid_campaign.index' => PaidCampaignResource::class,
        ];
        $permissions = [];

        foreach ($resources as $permission => $resource) {
            $allowed = hexa()->can($permission);
            $permissions[$permission] = $allowed;
            $permissions[$permission.'_url'] = $allowed ? $resource::getUrl('index') : null;
        }
        $permissions['task.view_all'] = hexa()->can('task.view_all');

        $summary = app(AdminOverviewDashboardData::class)->summary($permissions);
        $kpiSources = [
            'customer_record_count' => ['permission' => 'customer.index', 'resource' => CustomerResource::class],
            'unconverted_lead_count' => ['permission' => 'customer_lead.index', 'resource' => CustomerLeadResource::class],
            'unknown_lead_source_count' => ['permission' => 'customer_lead.index', 'resource' => CustomerLeadResource::class],
            'open_task_count' => ['permission' => 'task.index', 'resource' => TaskResource::class],
            'overdue_task_count' => ['permission' => 'task.index', 'resource' => TaskResource::class],
            'competitor_offer_snapshot_count' => ['permission' => 'competitor_offer.index', 'resource' => CompetitorOfferResource::class],
        ];

        foreach ($summary['kpis']['results'] as &$kpi) {
            $source = $kpiSources[$kpi['key']] ?? null;
            $kpi['source_url'] = null;

            if ($source !== null && ($permissions[$source['permission']] ?? false)) {
                $resource = $source['resource'];
                $kpi['source_url'] = $resource::getUrl('index');
            }

            $kpi['source_record_ids'] = $kpi['provenance']['source_record_ids'] ?? [];
            $kpi['source_record_ids_truncated'] = $kpi['provenance']['source_record_ids_truncated'] ?? false;
        }
        unset($kpi);

        return $summary;
    }

    /**
     * @param  class-string<Page>|null  $page
     */
    public function canOpenOverview(?string $page): bool
    {
        return $page !== null && is_subclass_of($page, Page::class) && $page::canAccess();
    }
}
