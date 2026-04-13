<?php

namespace App\Filament\Clusters\Products\Pages;

use App\Filament\Clusters\Products\ProductsCluster;
use Filament\Pages\Page;

class ProductsOverview extends Page
{
    protected string $view = 'filament.clusters.products.pages.products-overview';

    protected static ?string $cluster = ProductsCluster::class;
}
