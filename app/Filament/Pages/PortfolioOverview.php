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

class PortfolioOverview extends Page
{
    use HasHexaLite;

    protected static ?int $navigationSort = 0;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $title = 'Portfolio Overview';

    protected string $view = 'filament.clusters.portfolio.pages.portfolio-overview';

    protected static ?string $cluster = PortfolioCluster::class;

    public ?string $statusFilter = null;

    public ?string $typeFilter = null;

    public ?string $priceFilter = null;

    public function defineGates(): array
    {
        return [
            'portfolio-overview.view' => __('Allows viewing the portfolio overview'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('portfolio-overview.view') && hexa()->can('product.index');
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
