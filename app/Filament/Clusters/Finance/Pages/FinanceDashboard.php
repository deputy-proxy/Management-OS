<?php

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;

class FinanceDashboard extends Page
{
    protected string $view = 'filament.clusters.finance.pages.finance-dashboard';

    protected static ?string $cluster = FinanceCluster::class;
    
    use HasHexaLite;

    public function defineGates(): array
    {
        return [
            'finance-dashboard.index' => __('Allows viewing the competition list'),
            'finance-dashboard.create' => __('Allows creating a new competition'),
            'finance-dashboard.update' => __('Allows updating competitions'),
            'finance-dashboard.delete' => __('Allows deleting competitions'),
        ];
    }
}
