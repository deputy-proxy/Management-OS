<?php

namespace App\Filament\Clusters\Operations;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class OperationsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog;
    protected static ?int $navigationSort = 3;
}
