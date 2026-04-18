<?php

namespace App\Filament\Clusters\Products\Pages;

use App\Filament\Clusters\Products\ProductsCluster;
use Filament\Pages\Page;
use UnitEnum;

class ProductsOverview extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.products.pages.products-overview';
    protected static ?string $cluster = ProductsCluster::class;
}
