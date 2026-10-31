<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\CustomerInvoices\CustomerInvoiceResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\VendorInvoices\VendorInvoiceResource;
use App\Services\Finance\ReceivablesPayablesData;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class ReceivablesPayablesDashboard extends Page
{
    use HasHexaLite;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Receivables & Payables';

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?string $cluster = FinanceCluster::class;

    protected string $view = 'filament.clusters.finance.pages.receivables-payables-dashboard';

    public string $asOf = '';

    public function mount(): void
    {
        $this->asOf = CarbonImmutable::now('Europe/Bucharest')->toDateString();
    }

    public function defineGates(): array
    {
        return [
            'receivables-payables.view' => __('Allows viewing receivables and payables reporting'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('receivables-payables.view');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        try {
            $asOf = CarbonImmutable::createFromFormat('!Y-m-d', $this->asOf, 'Europe/Bucharest');
            if (! $asOf || $asOf->format('Y-m-d') !== $this->asOf) {
                $asOf = CarbonImmutable::now('Europe/Bucharest');
            }
        } catch (\Throwable) {
            $asOf = CarbonImmutable::now('Europe/Bucharest');
        }

        $report = app(ReceivablesPayablesData::class)->report($asOf);

        return ['report' => $report];
    }

    public function customerInvoiceUrl(int $invoiceId): ?string
    {
        return hexa()->can('customer_invoice.index')
            ? CustomerInvoiceResource::getUrl('view', ['record' => $invoiceId])
            : null;
    }

    public function vendorInvoiceUrl(int $invoiceId): ?string
    {
        return hexa()->can('vendor_invoice.index')
            ? VendorInvoiceResource::getUrl('view', ['record' => $invoiceId])
            : null;
    }

    public function transactionUrl(int $transactionId): ?string
    {
        return hexa()->can('transaction.index')
            ? TransactionResource::getUrl('view', ['record' => $transactionId])
            : null;
    }
}
