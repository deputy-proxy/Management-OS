<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\General\GeneralCluster;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;


class GeneralDashboard extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Overview';
    protected string $view = 'filament.clusters.human-resources.pages.human-resources-dashboard';
    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBriefcase;
    protected static ?string $cluster = GeneralCluster::class;
    protected static ?int $navigationSort = 0;

    use HasHexaLite;
    public function defineGates(): array
    {
        return [
            'general-dashboard.view' => __('Allows viewing the general dashboard'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('general-dashboard.view');
    }
}
