<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Services\ConsolidatedFinancialReportData;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class FinanceDashboard extends Page
{
    protected static ?int $navigationSort = 0;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected string $view = 'filament.clusters.finance.pages.finance-dashboard';

    protected static ?string $cluster = FinanceCluster::class;

    use HasHexaLite;

    public string $reportFrom = '';

    public string $reportTo = '';

    public string $reportBasis = 'all';

    public string $reportCurrency = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('Europe/Bucharest');
        $this->reportFrom = $today->startOfMonth()->toDateString();
        $this->reportTo = $today->toDateString();
    }

    public function defineGates(): array
    {
        return [
            'finance-dashboard.view' => __('Allows viewing the finance dashboard'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('finance-dashboard.view');
    }

    protected function getViewData(): array
    {
        $from = $this->validDate($this->reportFrom, CarbonImmutable::now('Europe/Bucharest')->startOfMonth());
        $to = $this->validDate($this->reportTo, CarbonImmutable::now('Europe/Bucharest'));

        return [
            'consolidatedReport' => app(ConsolidatedFinancialReportData::class)->report(
                $from,
                $to,
                $this->reportCurrency !== '' ? $this->reportCurrency : null,
                in_array($this->reportBasis, ['all', 'customer_documents', 'vendor_invoices', 'cash_movements'], true)
                    ? $this->reportBasis
                    : 'all',
            ),
        ];
    }

    private function validDate(string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Europe/Bucharest');

            return $date && $date->format('Y-m-d') === $value ? $date : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
