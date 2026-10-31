<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\CustomerInvoices\CustomerInvoiceResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\ProductRevenues\ProductRevenueResource;
use App\Filament\Resources\Products\ProductResource as ProductFilamentResource;
use App\Models\ProfitabilityCalculationRun;
use App\Services\ProfitabilityCalculationService;
use App\Services\ProfitabilityReportService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ProfitabilityPreview extends Page
{
    use HasHexaLite;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Profitability Reports';

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?string $cluster = FinanceCluster::class;

    protected string $view = 'filament.clusters.finance.pages.profitability-preview';

    public string $periodStart = '';

    public string $periodEnd = '';

    public ?int $selectedRunId = null;

    public ?int $productFilter = null;

    public ?string $currencyFilter = null;

    public function mount(): void
    {
        $today = CarbonImmutable::now('Europe/Bucharest');
        $this->periodStart = $today->startOfMonth()->toDateString();
        $this->periodEnd = $today->toDateString();
    }

    /** @return array<string, string> */
    public function defineGates(): array
    {
        return [
            'finance-profitability.view' => __('Allows viewing provisional profitability calculation runs'),
            'finance-profitability.calculate' => __('Allows creating provisional profitability calculation runs'),
            'finance-profitability.customer-view' => __('Allows viewing customer revenue attribution in provisional profitability reports'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('finance-profitability.view');
    }

    public function calculate(): void
    {
        abort_unless(hexa()->can('finance-profitability.calculate'), 403);

        try {
            $run = app(ProfitabilityCalculationService::class)->calculate($this->periodStart, $this->periodEnd);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->selectedRunId = (int) $run->getKey();
        $this->resetFilters();

        Notification::make()
            ->title('Provisional calculation saved')
            ->body('The run is immutable and remains explicitly provisional.')
            ->success()
            ->send();
    }

    public function selectRun(int $runId): void
    {
        abort_unless(hexa()->can('finance-profitability.view'), 403);
        abort_unless(ProfitabilityCalculationRun::query()->whereKey($runId)->exists(), 404);

        $this->selectedRunId = $runId;
        $this->resetFilters();
    }

    public function resetFilters(): void
    {
        $this->productFilter = null;
        $this->currencyFilter = null;
    }

    public function productUrl(int $productId): ?string
    {
        return hexa()->can('product.index')
            ? ProductFilamentResource::getUrl('view', ['record' => $productId])
            : null;
    }

    public function productRevenueUrl(int $revenueId): ?string
    {
        return hexa()->can('product_revenue.index')
            ? ProductRevenueResource::getUrl('view', ['record' => $revenueId])
            : null;
    }

    public function customerInvoiceUrl(int $invoiceId): ?string
    {
        return hexa()->can('customer_invoice.index')
            ? CustomerInvoiceResource::getUrl('view', ['record' => $invoiceId])
            : null;
    }

    public function customerUrl(int $customerId): ?string
    {
        return hexa()->can('customer.index')
            ? CustomerResource::getUrl('view', ['record' => $customerId])
            : null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $runs = ProfitabilityCalculationRun::query()
            ->with('results.product')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $selectedRun = $this->selectedRunId
            ? $runs->firstWhere('id', $this->selectedRunId)
            : $runs->first();
        $canViewCustomerDetails = hexa()->can('finance-profitability.customer-view')
            && hexa()->can('customer_invoice.index');
        $report = $selectedRun
            ? app(ProfitabilityReportService::class)->report($selectedRun, [
                'product_id' => $this->productFilter,
                'currency_code' => $this->currencyFilter,
            ], $canViewCustomerDetails)
            : null;

        return [
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'report' => $report,
            'canCalculate' => hexa()->can('finance-profitability.calculate'),
            'canViewCustomerDetails' => $canViewCustomerDetails,
        ];
    }
}
