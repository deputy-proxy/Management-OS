<?php

namespace Hexters\HexaLite\Resources\Roles\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $rawPermissions = collect(config('hexa-lite-roles'));
        $gateClusterMap = static::buildGateClusterMap();

        // Also build a map of cluster => [gate_keys...] for the toggle action
        $clusterGateKeys = [];
        $rawPermissions->each(function ($role) use ($gateClusterMap, &$clusterGateKeys) {
            $firstGate = array_key_first($role['names'] ?? []);
            $cluster = $gateClusterMap[$firstGate] ?? 'General';
            $key = Str::slug($role['name'], '_');
            $clusterGateKeys[$cluster][] = $key;
        });

        $grouped = $rawPermissions->groupBy(function ($role) use ($gateClusterMap) {
            $firstGate = array_key_first($role['names'] ?? []);
            return $gateClusterMap[$firstGate] ?? 'General';
        });

        $clusterSections = $grouped->map(function ($roles, $clusterLabel) use ($clusterGateKeys) {
            $gateKeys = $clusterGateKeys[$clusterLabel] ?? [];

            $permissionSections = $roles->map(function ($role) {
                $key = Str::slug($role['name'], '_');
                return Section::make(ucfirst($role['name']))
                    ->collapsed(false)
                    ->schema([
                        CheckboxList::make("gates.{$key}")
                            ->hiddenLabel()
                            ->bulkToggleable()
                            ->options(fn (): array => collect($role['names'] ?? [])
                                ->filter(fn ($label, $gate): bool => is_string($gate) && is_scalar($label))
                                ->map(fn ($label): string => (string) $label)
                                ->all()),
                    ]);
            })->values()->all();

            return Section::make($clusterLabel)
                ->collapsed(false)
                ->headerActions([
                    Action::make("select_all_{$clusterLabel}")
                        ->label(__('Select all'))
                        ->link()
                        ->action(function (Action $action, $livewire) use ($gateKeys) {
                            $data = $livewire->data;
                            foreach ($gateKeys as $key) {
                                $allOptions = array_keys(
                                    collect(config('hexa-lite-roles'))
                                        ->firstWhere('name', Str::replace('_', ' ', $key))['names'] ?? []
                                );
                                $data['gates'][$key] = $allOptions;
                            }
                            $livewire->data = $data;
                        }),
                    Action::make("deselect_all_{$clusterLabel}")
                        ->label(__('Deselect all'))
                        ->link()
                        ->color('gray')
                        ->action(function (Action $action, $livewire) use ($gateKeys) {
                            $data = $livewire->data;
                            foreach ($gateKeys as $key) {
                                $data['gates'][$key] = [];
                            }
                            $livewire->data = $data;
                        }),
                ])
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

            $clusterLabel = static::resolveClusterLabel($resourceClass);

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

    protected static function resolveClusterLabel(string $resourceClass): string
    {
        $clusterClass = null;

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

        try {
            if (method_exists($clusterClass, 'getNavigationLabel')) {
                return $clusterClass::getNavigationLabel();
            }
        } catch (\Throwable) {
            // fall through
        }

        $basename = class_basename($clusterClass);
        return Str::title(Str::replaceLast('Cluster', '', $basename));
    }
}