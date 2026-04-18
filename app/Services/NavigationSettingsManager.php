<?php

namespace App\Services;

use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\File;

class NavigationSettingsManager
{
    protected string $path;

    public function __construct()
    {
        $this->path = storage_path('app/navigation.json');
    }

    public function get(): array
    {
        if (! File::exists($this->path)) {
            return [];
        }

        return json_decode(File::get($this->path), true) ?? [];
    }

    public function save(array $settings): void
    {
        File::put($this->path, json_encode($settings, JSON_PRETTY_PRINT));
    }

    public function hasSettings(): bool
    {
        return File::exists($this->path) && ! empty($this->get());
    }

    public function reset(): void
    {
        if (File::exists($this->path)) {
            File::delete($this->path);
        }
    }

    public function getResourceMap(): array
    {
        $map = [];
        $settings = $this->get();

        foreach ($settings['clusters'] ?? [] as $clusterSort => $cluster) {
            foreach ($cluster['groups'] ?? [] as $groupSort => $group) {
                foreach ($group['resources'] ?? [] as $resourceSort => $resource) {
                    $map[$resource['class']] = [
                        'cluster'      => $cluster['class'] ?? null,
                        'cluster_label'=> $cluster['label'],
                        'group'        => $group['label'],
                        'sort'         => $resourceSort,
                        'cluster_sort' => $clusterSort,
                        'group_sort'   => $groupSort,
                    ];
                }
            }
        }

        return $map;
    }

    public function applyToBuilder(NavigationBuilder $nav): NavigationBuilder
    {
        if (! $this->hasSettings()) {
            $structure = $this->buildStructureFromPanel();
            $this->save($structure);
        }

        $structure = $this->get();
        $allGroups = [];

        foreach ($structure['clusters'] ?? [] as $cluster) {
            $clusterLabel = $cluster['label'];

            foreach ($cluster['groups'] ?? [] as $group) {
                $items = [];

                foreach ($group['resources'] ?? [] as $resource) {
                    $class = $resource['class'] ?? null;

                    if (! $class || ! class_exists($class)) {
                        continue;
                    }

                    try {
                        $isPage = ($resource['type'] ?? 'resource') === 'page';
                        $label  = $resource['label'] ?? (method_exists($class, 'getNavigationLabel') ? $class::getNavigationLabel() : $class);
                        $url    = $class::getUrl();

                        $icon = $resource['icon'] ?? null;
                        if (! $icon || ! str_starts_with($icon, 'heroicon-')) {
                            try { $icon = $class::getNavigationIcon(); } catch (\Throwable) {}
                        }
                        $icon = (is_string($icon) && str_starts_with($icon, 'heroicon-'))
                            ? $icon
                            : 'heroicon-o-squares-2x2';

                        $item = NavigationItem::make($label)
                            ->url($url)
                            ->icon($icon);

                        if (! $isPage) {
                            try {
                                $item->badge(fn () => $class::getNavigationBadge())
                                    ->badgeColor(fn () => $class::getNavigationBadgeColor());
                            } catch (\Throwable) {}
                        }

                        $items[] = $item;

                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("NavigationBuilder: skipped {$class} — " . $e->getMessage());
                        continue;
                    }
                }

                if (! empty($items)) {
                    $isGeneral     = $clusterLabel === 'General' && $cluster['class'] === null;
                    $navGroupLabel = $isGeneral
                        ? $group['label']
                        : $clusterLabel . ' › ' . $group['label'];

                    $allGroups[] = NavigationGroup::make($navGroupLabel)->items($items);
                }
            }
        }

        return $nav->groups($allGroups);
    }

    public function buildStructureFromPanel(): array
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

    public function syncToPhpFiles(array $structure): void
    {
        $appPath = realpath(app_path());

        foreach ($structure['clusters'] ?? [] as $cluster) {
            $clusterClass = $cluster['class'] ?? null;
            $isGeneral    = $clusterClass === null;

            foreach ($cluster['groups'] ?? [] as $group) {
                $groupLabel  = $group['label'];
                $isUngrouped = $groupLabel === 'Ungrouped';

                foreach ($group['resources'] ?? [] as $resource) {
                    $class = $resource['class'] ?? null;
                    $type  = $resource['type'] ?? 'resource';

                    if (! $class || ! class_exists($class)) {
                        continue;
                    }

                    try {
                        $reflection = new \ReflectionClass($class);
                        $filePath   = $reflection->getFileName();

                        if (! $filePath || ! file_exists($filePath)) {
                            continue;
                        }

                        // Only update files inside the app/ directory
                        $realFilePath = realpath($filePath);
                        if (! $realFilePath || ! str_starts_with($realFilePath, $appPath)) {
                            continue;
                        }

                        // Skip cluster classes themselves — they manage their own properties
                        if (is_subclass_of($class, \Filament\Clusters\Cluster::class)) {
                            continue;
                        }

                        $original = file_get_contents($filePath);
                        $contents = $original;

                        // Sync $navigationGroup
                        $contents = $this->syncProperty(
                            $contents,
                            'navigationGroup',
                            $isUngrouped ? null : $groupLabel,
                            $type
                        );

                        // Sync $cluster with use statement
                        $contents = $this->syncCluster(
                            $contents,
                            $isGeneral ? null : $clusterClass
                        );

                        // Validate syntax before writing
                        if (! $this->isValidPhp($contents)) {
                            \Illuminate\Support\Facades\Log::error(
                                "NavigationSync: syntax error after modification, skipping {$class}"
                            );
                            continue;
                        }

                        // Only write if something actually changed
                        if ($contents !== $original) {
                            file_put_contents($filePath, $contents);
                        }

                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("NavigationSync: could not update {$class} — " . $e->getMessage());
                    }
                }
            }
        }
    }

    protected function syncCluster(string $contents, ?string $clusterClass): string
    {
        $property = 'cluster';
        $typeHint = '?string';

        if ($clusterClass === null) {
            $newLine = "    protected static {$typeHint} \${$property} = null;";
        } else {
            // Extract short class name e.g. FinanceCluster
            $shortName = class_basename($clusterClass);
            $newLine   = "    protected static {$typeHint} \${$property} = {$shortName}::class;";
        }

        // Replace or insert the property
        $pattern = '/^(\s*)protected\s+static\s+[\w\\\\|?]+\s+\$' . preg_quote($property, '/') . '\s*=.*?;/m';

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $newLine, $contents);
        } else {
            $contents = preg_replace(
                '/(class\s+\w+[^{]*\{)/',
                "$1\n{$newLine}",
                $contents,
                1
            );
        }

        // Add use statement if cluster is set
        if ($clusterClass !== null) {
            $useStatement = "use {$clusterClass};";

            // Only add if not already present
            if (! str_contains($contents, $useStatement)) {
                // Insert after the last existing use statement
                if (preg_match('/^use\s+[^;]+;/m', $contents)) {
                    $contents = preg_replace(
                        '/((?:^use\s+[^;]+;\n)+)/m',
                        "$1{$useStatement}\n",
                        $contents,
                        1
                    );
                } else {
                    // No use statements yet — insert after namespace declaration
                    $contents = preg_replace(
                        '/(^namespace\s+[^;]+;)/m',
                        "$1\n\n{$useStatement}",
                        $contents,
                        1
                    );
                }
            }
        } else {
            // If cluster is null, remove any previously added cluster use statements
            // We only remove use statements for classes that look like cluster classes
            $contents = preg_replace('/^use\s+\S+Cluster[^;]*;\n/m', '', $contents);
        }

        return $contents;
    }

    protected function syncProperty(string $contents, string $property, ?string $value, string $classType = 'resource'): string
    {
        $isCluster = $property === 'cluster';

        $typeHint = $isCluster ? '?string' : 'string|UnitEnum|null';

        $newLine = $value === null
            ? "    protected static {$typeHint} \${$property} = null;"
            : "    protected static {$typeHint} \${$property} = '{$value}';";

        $pattern = '/^(\s*)protected\s+static\s+[\w\\\\|?]+\s+\$' . preg_quote($property, '/') . '\s*=.*?;/m';

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $newLine, $contents);
        } else {
            $contents = preg_replace(
                '/(class\s+\w+[^{]*\{)/',
                "$1\n{$newLine}",
                $contents,
                1
            );
        }

        // Inject "use UnitEnum;" if the type hint needs it and it's not already present
        if (! $isCluster && str_contains($typeHint, 'UnitEnum')) {
            $useStatement = 'use UnitEnum;';

            if (! str_contains($contents, $useStatement)) {
                if (preg_match('/^use\s+[^;]+;/m', $contents)) {
                    // Insert after the last existing use statement block
                    $contents = preg_replace(
                        '/((?:^use\s+[^;]+;\n)+)/m',
                        "$1{$useStatement}\n",
                        $contents,
                        1
                    );
                } else {
                    // No use statements — insert after namespace
                    $contents = preg_replace(
                        '/(^namespace\s+[^;]+;)/m',
                        "$1\n\n{$useStatement}",
                        $contents,
                        1
                    );
                }
            }
        }

        return $contents;
    }

protected function isValidPhp(string $contents): bool
{
    $tmp = tempnam(sys_get_temp_dir(), 'nav_sync_');
    file_put_contents($tmp, $contents);
    exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $returnCode);
    unlink($tmp);
    return $returnCode === 0;
}

    public function debugSync(): void
{
    $appPath  = realpath(app_path());
    $structure = $this->get();

    foreach ($structure['clusters'] ?? [] as $cluster) {
        $clusterClass = $cluster['class'] ?? null;
        $isGeneral    = $clusterClass === null;

        foreach ($cluster['groups'] ?? [] as $group) {
            $groupLabel  = $group['label'];
            $isUngrouped = $groupLabel === 'Ungrouped';

            foreach ($group['resources'] ?? [] as $resource) {
                $class = $resource['class'] ?? null;

                if (! $class || ! class_exists($class)) {
                    dump("SKIP — class not found: {$class}");
                    continue;
                }

                $reflection   = new \ReflectionClass($class);
                $filePath     = $reflection->getFileName();
                $realFilePath = realpath($filePath);

                dump([
                    'class'         => $class,
                    'filePath'      => $filePath,
                    'realFilePath'  => $realFilePath,
                    'inAppPath'     => str_starts_with($realFilePath, $appPath),
                    'isCluster'     => is_subclass_of($class, \Filament\Clusters\Cluster::class),
                    'clusterClass'  => $clusterClass,
                    'groupLabel'    => $groupLabel,
                    'isGeneral'     => $isGeneral,
                    'isUngrouped'   => $isUngrouped,
                ]);

                $original = file_get_contents($filePath);
                $contents = $original;

                $contents = $this->syncProperty($contents, 'navigationGroup', $isUngrouped ? null : $groupLabel, $resource['type'] ?? 'resource');
                $contents = $this->syncCluster($contents, $isGeneral ? null : $clusterClass);

                dump([
                    'changed'      => $contents !== $original,
                    'phpValid'     => $this->isValidPhp($contents),
                ]);
            }
        }
    }
}
}
