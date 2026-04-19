<?php

namespace App\Filament\Clusters\General;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Filament\Pages\Enums\SubNavigationPosition;

class GeneralCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;
    protected static ?int $navigationSort = 0;
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;
}
