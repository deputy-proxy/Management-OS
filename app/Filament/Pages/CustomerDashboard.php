<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\CustomerContracts\CustomerContractResource;
use App\Filament\Resources\CustomerInteractions\CustomerInteractionResource;
use App\Filament\Resources\CustomerInvoices\CustomerInvoiceResource;
use App\Filament\Resources\CustomerLeads\CustomerLeadResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Services\CustomerDashboardData;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class CustomerDashboard extends Page
{
    use HasHexaLite;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.clusters.finance.pages.customer-dashboard';

    protected static ?string $cluster = FinanceCluster::class;

    public function defineGates(): array
    {
        return [
            'customer-dashboard.view' => __('Allows viewing the customer dashboard'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('customer-dashboard.view');
    }

    /**
     * Only expose destinations when the matching resource's list permission is granted.
     *
     * @return array<string, string|null>
     */
    public function getAuthorizedResourceUrls(): array
    {
        return [
            'customers' => hexa()->can('customer.index') ? CustomerResource::getUrl('index') : null,
            'leads' => hexa()->can('customer_lead.index') ? CustomerLeadResource::getUrl('index') : null,
            'contracts' => hexa()->can('customer_contract.index') ? CustomerContractResource::getUrl('index') : null,
            'interactions' => hexa()->can('customer_interaction.index') ? CustomerInteractionResource::getUrl('index') : null,
            'invoices' => hexa()->can('customer_invoice.index') ? CustomerInvoiceResource::getUrl('index') : null,
            'tasks' => hexa()->can('task.index') ? TaskResource::getUrl('index') : null,
        ];
    }

    public function getDashboardSummary(): array
    {
        return app(CustomerDashboardData::class)->summary(
            includeFollowUpTasks: hexa()->can('task.index'),
            viewAllTasks: hexa()->can('task.view_all'),
            viewerId: auth()->id() ? (int) auth()->id() : null,
            includeContractReview: hexa()->can('customer_contract.index'),
        );
    }
}
