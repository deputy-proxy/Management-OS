<?php

namespace Hexters\HexaLite\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\GridDirection;
use Illuminate\Support\Str;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $rawPermissions = collect(config('hexa-lite-roles'));

        // Build a map of gate_key => cluster label
        $gateClusterMap = static::buildGateClusterMap();

        // Group permission sections by cluster
        $grouped = $rawPermissions->groupBy(function ($role) use ($gateClusterMap) {
            $key = Str::slug($role['name'], '_');
            // Find if any gate in this role belongs to a cluster
            $firstGate = array_key_first($role['names'] ?? []);
            return $gateClusterMap[$firstGate] ?? 'General';
        });

        $clusterSections = $grouped->map(function ($roles, $clusterLabel) {
            $permissionSections = $roles->map(function ($role) {
                $key = Str::slug($role['name'], '_');
                return Section::make(ucfirst($role['name']))
                    ->collapsed(true)
                    ->schema([
                        CheckboxList::make("gates.{$key}")
                            ->searchable()
                            ->columns(2)
                            ->gridDirection(GridDirection::Row)
                            ->hiddenLabel()
                            ->bulkToggleable()
                            ->options($role['names']),
                    ]);
            })->values()->all();

            return Section::make($clusterLabel)
                ->collapsed(false)
                ->schema($permissionSections);
        })->values()->all();

        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(__('Role Name'))
                    ->maxLength(100)
                    ->placeholder(__('Supervisor'))
                    ->required(),
                ViewField::make('checkall')
                    ->label(__('Check / Uncheck all'))
                    ->view('hexa::role.toggle-button'),
                ...$clusterSections,
            ]);
    }

    /**
     * Scans all registered Filament resources and maps each gate key
     * to the label of the cluster the resource belongs to.
     *
     * @return array<string, string>  [ 'discount.index' => 'Portfolio', ... ]
     */
    protected static function buildGateClusterMap(): array
    {
        $map = [];

        try {
            $panel = filament()->getCurrentPanel();
            $resources = $panel?->getResources() ?? [];
        } catch (\Throwable) {
            return $map;
        }

        foreach ($resources as $resourceClass) {
            if (!class_exists($resourceClass)) {
                continue;
            }

            // Resolve cluster label
            $clusterLabel = static::resolveClusterLabel($resourceClass);

            // Check if resource uses HasHexaLite and has defineGates()
            if (!method_exists($resourceClass, 'defineGates')) {
                continue;
            }

            try {
                $instance = app($resourceClass);
                $gates = $instance->defineGates();
            } catch (\Throwable) {
                continue;
            }

            foreach (array_keys($gates) as $gate) {
                $map[$gate] = $clusterLabel;
            }
        }

        return $map;
    }

    /**
     * Resolves a human-readable cluster label from a resource class.
     */
    protected static function resolveClusterLabel(string $resourceClass): string
    {
        $clusterClass = null;

        // Read the static $cluster property safely
        try {
            $ref = new \ReflectionClass($resourceClass);
            if ($ref->hasProperty('cluster')) {
                $prop = $ref->getProperty('cluster');
                $prop->setAccessible(true);
                $clusterClass = $prop->getValue();
            }
        } catch (\Throwable) {
            // ignore
        }

        if (!$clusterClass || !class_exists($clusterClass)) {
            return 'General';
        }

        // Try to get the cluster's navigation label or derive from class name
        try {
            if (method_exists($clusterClass, 'getNavigationLabel')) {
                return $clusterClass::getNavigationLabel();
            }
        } catch (\Throwable) {
            // fall through
        }

        // Fallback: derive label from class name (e.g. PortfolioCluster → Portfolio)
        $basename = class_basename($clusterClass);
        return Str::title(Str::replaceLast('Cluster', '', $basename));
    }
}