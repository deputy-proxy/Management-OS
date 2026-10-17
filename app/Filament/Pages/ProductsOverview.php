<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Portfolio\PortfolioCluster;
use App\Filament\Resources\Camps\CampResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\ProductPrices\ProductPriceResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Workshops\WorkshopResource;
use App\Services\ProductsOverviewData;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class ProductsOverview extends Page
{
    use HasHexaLite;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Products Overview';

    protected string $view = 'filament.clusters.portfolio.pages.products-overview';

    protected static ?string $cluster = PortfolioCluster::class;

    public ?string $statusFilter = null;

    public ?string $typeFilter = null;

    public ?string $priceFilter = null;

    public function defineGates(): array
    {
        return [
            'products-overview.view' => __('Allows viewing the products overview'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('products-overview.view') && hexa()->can('product.index');
    }

    /** @return array<string, string|null> */
    public function getAuthorizedResourceUrls(): array
    {
        return [
            'products' => hexa()->can('product.index') ? ProductResource::getUrl('index') : null,
            'prices' => hexa()->can('product_price.index') ? ProductPriceResource::getUrl('index') : null,
            'camps' => hexa()->can('camp.index') ? CampResource::getUrl('index') : null,
            'courses' => hexa()->can('course.index') ? CourseResource::getUrl('index') : null,
            'events' => hexa()->can('event.index') ? EventResource::getUrl('index') : null,
            'workshops' => hexa()->can('workshop.index') ? WorkshopResource::getUrl('index') : null,
        ];
    }

    public function getProductUrl(int $productId): ?string
    {
        return hexa()->can('product.index') ? ProductResource::getUrl('view', ['record' => $productId]) : null;
    }

    public function getProductableUrl(string $type, int $recordId): ?string
    {
        $resources = [
            'camp' => ['permission' => 'camp.index', 'resource' => CampResource::class],
            'course' => ['permission' => 'course.index', 'resource' => CourseResource::class],
            'event' => ['permission' => 'event.index', 'resource' => EventResource::class],
            'workshop' => ['permission' => 'workshop.index', 'resource' => WorkshopResource::class],
        ];

        if (! isset($resources[$type]) || ! hexa()->can($resources[$type]['permission'])) {
            return null;
        }

        return $resources[$type]['resource']::getUrl('view', ['record' => $recordId]);
    }

    /** @return array<string, mixed> */
    public function getDashboardSummary(): array
    {
        return app(ProductsOverviewData::class)->summary(
            statusFilter: $this->statusFilter,
            typeFilter: $this->typeFilter,
            priceFilter: $this->priceFilter,
        );
    }
}