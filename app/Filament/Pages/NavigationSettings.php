<?php

namespace App\Filament\Pages;

use App\Services\NavigationSettingsManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel\Concerns\HasNavigation;
use Illuminate\Support\Facades\File;
use UnitEnum;

class NavigationSettings extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Navigation Settings';
    //protected static ?string $navigationGroup = 'Settings';
    protected string $view = 'filament.pages.navigation-settings';

    // The editable state, bound to the view
    public array $structure = [];

    public function mount(): void
    {
        $manager = app(NavigationSettingsManager::class);
        $saved   = $manager->get();

        if (!empty($saved)) {
            $this->structure = $saved;
        } else {
            // Bootstrap from current Filament navigation
            $this->structure = $this->buildStructureFromPanel();
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

    public function reorderResources(int $clusterIndex, int $groupIndex, array $order): void
{
    $reordered = [];
    foreach ($order as $index) {
        $reordered[] = $this->structure['clusters'][$clusterIndex]['groups'][$groupIndex]['resources'][$index];
    }
    $this->structure['clusters'][$clusterIndex]['groups'][$groupIndex]['resources'] = $reordered;
}

    protected function buildStructureFromPanel(): array
{
    $panel     = filament()->getCurrentPanel();
    $resources = $panel->getResources();
    $pages     = $panel->getPages();
    $clusters  = $panel->getClusters();

    $grouped = [];

    // Seed all known clusters first
    foreach ($clusters as $clusterClass) {
        $label = $clusterClass::getNavigationLabel();
        if (! isset($grouped[$label])) {
            $grouped[$label] = [
                'class'  => $clusterClass,
                'groups' => [],
            ];
        }
    }

    // Ensure the no-cluster bucket exists
    if (! isset($grouped['__none__'])) {
        $grouped['__none__'] = [
            'class'  => null,
            'groups' => [],
        ];
    }

    // Add resources
    foreach ($resources as $resourceClass) {
        $clusterClass = $resourceClass::getCluster();
        $clusterKey   = $clusterClass
            ? $clusterClass::getNavigationLabel()
            : '__none__';

        $groupLabel = $resourceClass::getNavigationGroup() ?? '__ungrouped__';

        $rawIcon = null;
        try { $rawIcon = $resourceClass::getNavigationIcon(); } catch (\Throwable) {}

        $icon = (is_string($rawIcon) && str_starts_with($rawIcon, 'heroicon-'))
            ? $rawIcon
            : 'heroicon-o-squares-2x2';

        $grouped[$clusterKey]['groups'][$groupLabel][] = [
            'class' => $resourceClass,
            'label' => $resourceClass::getNavigationLabel(),
            'icon'  => $icon,
            'type'  => 'resource',
        ];
    }

    // Add pages that appear in navigation
    foreach ($pages as $pageClass) {
        try {
            if (! $pageClass::shouldRegisterNavigation()) {
                continue;
            }
        } catch (\Throwable) {
            continue;
        }

        $clusterClass = null;
        try { $clusterClass = $pageClass::getCluster(); } catch (\Throwable) {}

        $clusterKey = $clusterClass
            ? $clusterClass::getNavigationLabel()
            : '__none__';

        $groupLabel = null;
        try { $groupLabel = $pageClass::getNavigationGroup(); } catch (\Throwable) {}
        $groupLabel = $groupLabel ?? '__ungrouped__';

        $rawIcon = null;
        try { $rawIcon = $pageClass::getNavigationIcon(); } catch (\Throwable) {}

        $icon = (is_string($rawIcon) && str_starts_with($rawIcon, 'heroicon-'))
            ? $rawIcon
            : 'heroicon-o-squares-2x2';

        $label = null;
        try { $label = $pageClass::getNavigationLabel(); } catch (\Throwable) {}
        $label = $label ?? $pageClass;

        $grouped[$clusterKey]['groups'][$groupLabel][] = [
            'class' => $pageClass,
            'label' => $label,
            'icon'  => $icon,
            'type'  => 'page',
        ];
    }

    $structure = ['clusters' => []];

    foreach ($grouped as $clusterKey => $clusterData) {
        $clusterEntry = [
            'label'  => $clusterKey === '__none__' ? 'General' : $clusterKey,
            'class'  => $clusterData['class'],
            'groups' => [],
        ];

        foreach ($clusterData['groups'] as $groupLabel => $resources) {
            $clusterEntry['groups'][] = [
                'label'     => $groupLabel === '__ungrouped__' ? 'Ungrouped' : $groupLabel,
                'resources' => $resources,
            ];
        }

        if (! empty($clusterEntry['groups']) || $clusterData['class'] !== null) {
            $structure['clusters'][] = $clusterEntry;
        }
    }

    return $structure;
}
}