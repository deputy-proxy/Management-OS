<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use Filament\Pages\Page;
use UnitEnum;
use Hexters\HexaLite\HasHexaLite;

class ContentManagement extends Page
{
    protected static ?int $navigationSort = 0;
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.marketing.pages.content-management';
    protected static ?string $cluster = MarketingCluster::class;

    use HasHexaLite;
    public function defineGates(): array
    {
        return [
            'content-management.view' => __('Allows viewing the content management page'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('content-management.view');
    }
}
