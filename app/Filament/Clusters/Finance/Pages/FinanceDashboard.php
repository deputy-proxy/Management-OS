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
            'finance-dashboard.view' => __('Allows viewing the finance dashboard'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('finance-dashboard.view');
    }
}
