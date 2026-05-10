<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Portfolio\PortfolioCluster;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class PortfolioOverview extends Page
{
    protected static ?int $navigationSort = 0;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.portfolio.pages.portfolio-overview';
    protected static ?string $cluster = PortfolioCluster::class;
    
    use HasHexaLite;
    public function defineGates(): array
    {
        return [
            'portfolio-overview.view' => __('Allows viewing the portfolio overview'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('portfolio-overview.view');
    }
}
