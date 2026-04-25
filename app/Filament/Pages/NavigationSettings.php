<?php

namespace App\Filament\Pages;

use App\Services\NavigationSettingsManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;
class NavigationSettings extends Page
{
    protected static ?string $cluster = null;
    protected static string|UnitEnum|null $navigationGroup = 'System';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Navigation Settings';
    protected string $view = 'filament.pages.navigation-settings';

    public array $structure = [];

    public function mount(): void
    {
        $manager = app(NavigationSettingsManager::class);

        if ($manager->hasSettings()) {
            $this->structure = $manager->get();
        } else {
            $this->structure = $manager->buildStructureFromPanel();
            $manager->save($this->structure);
        }
    }

    public function save(): void
    {
        $manager = app(NavigationSettingsManager::class);
        $manager->save($this->structure);
        $manager->syncToPhpFiles($this->structure);

        Notification::make()
            ->title('Navigation settings saved')
            ->success()
            ->send();
    }

    public function renameGroup(int $clusterIndex, int $groupIndex, string $label): void
    {
        $this->structure['clusters'][$clusterIndex]['groups'][$groupIndex]['label'] = $label;
    }

    public function renameCluster(int $clusterIndex, string $label): void
    {
        $this->structure['clusters'][$clusterIndex]['label'] = $label;
    }

    public function reorderClusters(array $order): void
    {
        $reordered = [];
        foreach ($order as $index) {
            $reordered[] = $this->structure['clusters'][$index];
        }
        $this->structure['clusters'] = $reordered;
    }

    public function reorderGroups(int $clusterIndex, array $order): void
    {
        $reordered = [];
        foreach ($order as $index) {
            $reordered[] = $this->structure['clusters'][$clusterIndex]['groups'][$index];
        }
        $this->structure['clusters'][$clusterIndex]['groups'] = $reordered;
    }

    public function swapResources(int $clusterIndex, int $groupIndex, int $indexA, int $indexB): void
    {
        $resources = &$this->structure['clusters'][$clusterIndex]['groups'][$groupIndex]['resources'];

        if (! isset($resources[$indexA]) || ! isset($resources[$indexB])) {
            return;
        }

        [$resources[$indexA], $resources[$indexB]] = [$resources[$indexB], $resources[$indexA]];
    }

    public function moveResource(
        int $fromCluster, int $fromGroup, int $resourceIndex,
        int $toCluster,   int $toGroup
    ): void {
        $resource = $this->structure['clusters'][$fromCluster]['groups'][$fromGroup]['resources'][$resourceIndex];

        array_splice(
            $this->structure['clusters'][$fromCluster]['groups'][$fromGroup]['resources'],
            $resourceIndex, 1
        );

        $this->structure['clusters'][$toCluster]['groups'][$toGroup]['resources'][] = $resource;
    }

    public function addGroup(int $clusterIndex, string $label = 'New Group'): void
    {
        $this->structure['clusters'][$clusterIndex]['groups'][] = [
            'label'     => $label,
            'resources' => [],
        ];
    }

    public function removeGroup(int $clusterIndex, int $groupIndex): void
    {
        array_splice($this->structure['clusters'][$clusterIndex]['groups'], $groupIndex, 1);
    }
}