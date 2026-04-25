<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use Filament\Pages\Page;
use UnitEnum;

class MarketAnalysis extends Page
{
    protected static ?int $navigationSort = 7;
    protected static string|UnitEnum|null $navigationGroup = 'Market Analysis';
    protected string $view = 'filament.clusters.marketing.pages.market-analysis';
    protected static ?string $cluster = MarketingCluster::class;
}
