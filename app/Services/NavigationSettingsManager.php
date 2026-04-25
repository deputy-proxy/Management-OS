<?php

namespace App\Services;

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

    public function buildStructureFromPanel(): array
    {
        $panel     = filament()->getCurrentPanel();
        $resources = $panel->getResources();
        $pages     = $panel->getPages();
        $clusters  = $panel->getClusters();

        $grouped = [];

        foreach ($clusters as $clusterClass) {
            $label = $clusterClass::getNavigationLabel();
            if (! isset($grouped[$label])) {
                $grouped[$label] = [
                    'class'  => $clusterClass,
                    'groups' => [],
                ];
            }
        }

        if (! isset($grouped['__none__'])) {
            $grouped['__none__'] = [
                'class'  => null,
                'groups' => [],
            ];
        }

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
            $clusterClass     = $cluster['class'] ?? null;
            $isGeneral        = $clusterClass === null;
            $clusterSortIndex = 0;

            foreach ($cluster['groups'] ?? [] as $group) {
                $groupLabel  = $group['label'];
                $isUngrouped = $groupLabel === 'Ungrouped';

                foreach ($group['resources'] ?? [] as $resource) {
                    $class = $resource['class'] ?? null;
                    $type  = $resource['type'] ?? 'resource';

                    if (! $class || ! class_exists($class)) {
                        $clusterSortIndex++;
                        continue;
                    }

                    try {
                        $reflection = new \ReflectionClass($class);
                        $filePath   = $reflection->getFileName();

                        if (! $filePath || ! file_exists($filePath)) {
                            $clusterSortIndex++;
                            continue;
                        }

                        $realFilePath = realpath($filePath);
                        if (! $realFilePath || ! str_starts_with($realFilePath, $appPath)) {
                            $clusterSortIndex++;
                            continue;
                        }

                        if (is_subclass_of($class, \Filament\Clusters\Cluster::class)) {
                            continue;
                        }

                        $original = file_get_contents($filePath);
                        $contents = $original;

                        $contents = $this->syncProperty(
                            $contents,
                            'navigationGroup',
                            $isUngrouped ? null : $groupLabel,
                            $type
                        );

                        $contents = $this->syncIntProperty(
                            $contents,
                            'navigationSort',
                            $clusterSortIndex
                        );

                        $contents = $this->syncCluster(
                            $contents,
                            $isGeneral ? null : $clusterClass
                        );

                        $valid   = $this->isValidPhp($contents);
                        $changed = $contents !== $original;

                        if (! $valid) {
                            \Illuminate\Support\Facades\Log::error(
                                "NavigationSync: syntax error after modification, skipping {$class}"
                            );
                            $clusterSortIndex++;
                            continue;
                        }

                        if ($changed) {
                            file_put_contents($filePath, $contents);
                        }

                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("NavigationSync: could not update {$class} — " . $e->getMessage());
                    }

                    $clusterSortIndex++;
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
            $shortName = class_basename($clusterClass);
            $newLine   = "    protected static {$typeHint} \${$property} = {$shortName}::class;";
        }

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

        if ($clusterClass !== null) {
            $useStatement = "use {$clusterClass};";
            if (! str_contains($contents, $useStatement)) {
                $contents = $this->insertUseStatement($contents, $useStatement);
            }
        } else {
            $contents = preg_replace('/^use\s+\S+Cluster[^;]*;\n/m', '', $contents);
        }

        return $contents;
    }

    protected function syncProperty(string $contents, string $property, ?string $value, string $classType = 'resource'): string
    {
        $isCluster = $property === 'cluster';
        $typeHint  = $isCluster ? '?string' : 'string|UnitEnum|null';

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

        if (! $isCluster && str_contains($typeHint, 'UnitEnum')) {
            $useStatement = 'use UnitEnum;';
            if (! str_contains($contents, $useStatement)) {
                $contents = $this->insertUseStatement($contents, $useStatement);
            }
        }

        return $contents;
    }

    protected function syncIntProperty(string $contents, string $property, int $value): string
    {
        $newLine = "    protected static ?int \${$property} = {$value};";

        $pattern = '/^(\s*)protected\s+static\s+[\w\\\\|?]+\s+\$' . preg_quote($property, '/') . '\s*=.*?;/m';

        if (preg_match($pattern, $contents)) {
            return preg_replace($pattern, $newLine, $contents);
        }

        return preg_replace(
            '/(class\s+\w+[^{]*\{)/',
            "$1\n{$newLine}",
            $contents,
            1
        );
    }

    protected function insertUseStatement(string $contents, string $useStatement): string
    {
        if (preg_match_all('/^use\s+[^;]+;/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch      = end($matches[0]);
            $insertPosition = $lastMatch[1] + strlen($lastMatch[0]);

            return substr($contents, 0, $insertPosition)
                . "\n" . $useStatement
                . substr($contents, $insertPosition);
        }

        return preg_replace(
            '/(^namespace\s+[^;]+;)/m',
            "$1\n\n{$useStatement}",
            $contents,
            1
        );
    }

    protected function isValidPhp(string $contents): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'nav_sync_');
        file_put_contents($tmp, $contents);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $returnCode);
        unlink($tmp);
        return $returnCode === 0;
    }
}