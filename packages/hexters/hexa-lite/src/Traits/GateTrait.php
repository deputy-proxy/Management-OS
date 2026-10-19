<?php

namespace Hexters\HexaLite\Traits;

use Filament\Facades\Filament;
use Filament\Panel;
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

    protected function mergeAccess($access): array
    {
        $gates = [];
        foreach ($access as $accessGroup) {
            foreach ($accessGroup as $access) {
                $gates[] = $access;
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
                    Gate::define($gate, function ($user) use ($gate, $panel) {
                        if (! method_exists($user, 'roles')) {
                            return false;
                        }

                        if ($tenant = Filament::getTenant()) {
                            $roles = $user->roles()->whereBelongsTo($tenant)->get();
                        } else {
                            $roles = $user->roles;
                        }

                        // The configured owner role is the explicit superadmin role.
                        // New permission keys therefore do not silently lock the owner out.
                        $ownerRole = trim((string) config('hexa.super_admin_role', 'Owner'));
                        if ($ownerRole !== '' && $roles->contains(
                            fn ($role) => Str::lower(trim((string) $role->name)) === Str::lower($ownerRole)
                        )) {
                            return true;
                        }

                        if ($roles->isNotEmpty()) {
                            $allowedGates = [];
                            foreach ($this->mergeAccess($roles->pluck('access')) as $accessGroup) {
                                foreach ($accessGroup as $access) {
                                    $allowedGates[] = $access;
                                }
                            }

                            return in_array($gate, $allowedGates, true);
                        }

                        // Preserve HexaLite's existing no-role superadmin behavior.
                        return true;
                    });
                });
            });
    }
}
