<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\BankStatementImportItems\BankStatementImportItemResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\Finance\FinancialReconciliationReportData;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class FinancialReconciliationDashboard extends Page
{
    use HasHexaLite;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Financial Reconciliation';

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?string $cluster = FinanceCluster::class;

    protected string $view = 'filament.clusters.finance.pages.financial-reconciliation-dashboard';

    public string $reportFrom = '';

    public string $reportTo = '';

    public string $reportAccount = '';

    public string $reportCurrency = '';

    public string $reportDecision = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('Europe/Bucharest');
        $this->reportFrom = $today->startOfMonth()->toDateString();
        $this->reportTo = $today->toDateString();
    }

    public function defineGates(): array
    {
        return [
            'financial-reconciliation.view' => __('Allows viewing read-only financial reconciliation diagnostics'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('financial-reconciliation.view');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $today = CarbonImmutable::now('Europe/Bucharest');
        $from = $this->validDate($this->reportFrom, $today->startOfMonth());
        $to = $this->validDate($this->reportTo, $today);
        $accountId = filter_var($this->reportAccount, FILTER_VALIDATE_INT);

        return [
            'report' => app(FinancialReconciliationReportData::class)->report(
                $from,
                $to,
                is_int($accountId) && $accountId > 0 ? $accountId : null,
                $this->reportCurrency !== '' ? $this->reportCurrency : null,
                $this->reportDecision !== '' ? $this->reportDecision : null,
            ),
            'canViewSources' => hexa()->can('transaction.index'),
        ];
    }

    public function statementItemUrl(int $itemId): ?string
    {
        return hexa()->can('transaction.index')
            ? BankStatementImportItemResource::getUrl('view', ['record' => $itemId])
            : null;
    }

    public function transactionUrl(int $transactionId): ?string
    {
        return hexa()->can('transaction.index')
            ? TransactionResource::getUrl('view', ['record' => $transactionId])
            : null;
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
