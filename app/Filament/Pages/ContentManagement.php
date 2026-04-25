<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use Filament\Pages\Page;
use UnitEnum;

class ContentManagement extends Page
{
    protected static ?int $navigationSort = 0;
    protected static string|UnitEnum|null $navigationGroup = 'Content Management';
    protected string $view = 'filament.clusters.marketing.pages.content-management';
    protected static ?string $cluster = MarketingCluster::class;
}
