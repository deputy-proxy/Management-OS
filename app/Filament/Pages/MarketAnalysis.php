<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use Filament\Pages\Page;
use UnitEnum;
use Hexters\HexaLite\HasHexaLite;

class MarketAnalysis extends Page
{
    protected static ?int $navigationSort = 1;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.marketing.pages.market-analysis';
    protected static ?string $cluster = MarketingCluster::class;
    use HasHexaLite;
    public function defineGates(): array
    {
        return [
            'market-analysis.view' => __('Allows viewing the market analysis page'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('market-analysis.view');
    }
}
