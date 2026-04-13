<?php

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use Filament\Pages\Page;

class FinanceDashboard extends Page
{
    protected string $view = 'filament.clusters.finance.pages.finance-dashboard';

    protected static ?string $cluster = FinanceCluster::class;
}
