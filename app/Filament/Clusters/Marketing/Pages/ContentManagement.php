<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use Filament\Pages\Page;

class ContentManagement extends Page
{
    protected string $view = 'filament.clusters.marketing.pages.content-management';

    protected static ?string $cluster = MarketingCluster::class;
}
