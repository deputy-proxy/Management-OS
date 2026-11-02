<?php

namespace Hexters\\HexaLite\\Traits;

use Filament\\Facades\\Filament;
use Filament\\Panel;
use Illuminate\\Support\\Arr;
use Illuminate\\Support\\Facades\\Config;
use Illuminate\\Support\\Facades\\Gate;
use Illuminate\\Support\\Str;

trait GateTrait
{
    public function callGates(?Panel $panel)
    {
        $panel = $panel ?? Filament::getCurrentPanel();

        return collect([
            ...array_values($panel->getPages()),
            ...array_values($panel->getResources()),
        ])->filter(fn ($item) => method_exists(app($item), 'roleName'));
    }

    public function registerGateList(Panel $panel)
    {
        $rolesData = $this->callGates($panel)->map(function ($component) {
            $data['name'] = app($component)->roleName();
            $data['names'] = app($component)->defineGates();

            return $data;
        });

        Config::set(['hexa-lite-roles' => $rolesData]);
    }

    public function gates(Panel $panel)
    {
        $collections = $this->callGates($panel)
            ->map(fn ($item) => collect(app($item)->gateIndexs()))
            ->toArray();

        $gates = [];
        foreach ($collections as $items) {
            foreach ($items as $item) {
                $gates[] = $item;
            }
        }

        return $gates;
    }

    protected function registerGates(Panel $panel)
    {
        collect($this->callGates($panel))
            ->map(fn ($item) => collect(app($item)->gateIndexs()))
            ->each(function ($gates) use ($panel) {
                collect($gates)->each(function ($gate) use ($panel) {
                    Gate::define($gate, function ($user) use ($gate) {
                        if (! method_exists($user, 'roles')) {
                            return false;
                        }

                        // Always query the relationship so authorization does not
                        // depend on whether a stale roles relation was loaded earlier.
                        $rolesQuery = $user->roles();

                        if ($tenant = Filament::getTenant()) {
                            $rolesQuery->whereBelongsTo($tenant);
                        }

                        $roles = $rolesQuery->get();

                        // The configured owner role bypasses individual permission keys.
                        $ownerRole = trim((string) config('hexa.super_admin_role', 'Owner'));

                        if ($ownerRole !== '' && $roles->contains(
                            fn ($role) => Str::lower(trim((string) $role->name)) === Str::lower($ownerRole)
                        )) {
                            return true;
                        }

                        // Keep the existing HexaLite behavior for users without roles.
                        if ($roles->isEmpty()) {
                            return true;
                        }

                        // HexaLite stores permissions as nested arrays grouped by
                        // resource. Flatten them to permission-key strings before
                        // comparing; iterating the flattened strings as arrays caused
                        // valid permissions to be rejected.
                        $allowedGates = $roles
                            ->flatMap(fn ($role) => Arr::flatten($role->access ?? []))
                            ->filter(fn ($permission) => is_string($permission))
                            ->values()
                            ->all();

                        return in_array($gate, $allowedGates, true);
                    });
                });
            });
    }
}
