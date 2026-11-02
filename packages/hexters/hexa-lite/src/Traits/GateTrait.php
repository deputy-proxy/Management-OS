<?php

namespace Hexters\HexaLite\Traits;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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

                        // Query the relationship afresh so authorization does not
                        // depend on a stale, previously loaded roles relation.
                        $rolesQuery = $user->roles();

                        if ($tenant = Filament::getTenant()) {
                            $rolesQuery->whereBelongsTo($tenant);
                        }

                        $roles = $rolesQuery->get();

                        // The configured Owner role bypasses individual permission keys.
                        $ownerRole = trim((string) config('hexa.super_admin_role', 'Owner'));

                        if ($ownerRole !== '' && $roles->contains(
                            fn ($role) => Str::lower(trim((string) $role->name)) === Str::lower($ownerRole)
                        )) {
                            return true;
                        }

                        // Preserve HexaLite's existing no-role superadmin behavior.
                        if ($roles->isEmpty()) {
                            return true;
                        }

                        // Permissions are nested by resource. Flatten them to strings
                        // before comparing them with the requested gate.
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
