<?php

namespace App\Filament\Clusters\Academic;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Filament\Pages\Enums\SubNavigationPosition;

use UnitEnum;

class AcademicCluster extends Cluster
{
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;
    protected static ?int $navigationSort = 4;
}
