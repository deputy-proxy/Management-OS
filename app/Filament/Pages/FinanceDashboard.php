<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
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
