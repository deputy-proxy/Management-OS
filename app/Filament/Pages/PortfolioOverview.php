<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Portfolio\PortfolioCluster;
use Filament\Pages\Page;
use UnitEnum;

class PortfolioOverview extends Page
{
    protected static ?int $navigationSort = 22;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.portfolio.pages.portfolio-overview';
    protected static ?string $cluster = PortfolioCluster::class;
}
