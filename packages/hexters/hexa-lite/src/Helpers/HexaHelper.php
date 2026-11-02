<?php

namespace Hexters\\HexaLite\\Helpers;

use Exception;
use Filament\\Facades\\Filament;
use Filament\\Panel;
use Illuminate\\Contracts\\Auth\\Authenticatable;
use Illuminate\\Support\\Arr;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\Gate;
use Illuminate\\Support\\Str;

class HexaHelper
{

    private ?Authenticatable $user;

    public function guard(): string
    {
        return explode('_', Auth::guard()->getName())[1] ?? 'web';
    }

    public function can(array|string $gates): bool
    {
        return empty($this->user) ? Gate::allows($gates) : $this->userCan($this->user, $gates);
    }


    public function panelGates(Panel $panel): array
    {
        try {
            return $panel->getPlugin('filament-hexa')->gates($panel);
        } catch (Exception $e) {
            return [];
        }
    }

    public function user(Authenticatable $user)
    {
        $this->user = $user;
        return $this;
    }


    private function userCan(Authenticatable $user, array|string $permissions): bool
    {
        if (! method_exists($user, 'roles')) {
            return false;
        }

        // Keep direct helper checks consistent with the GateTrait authorization
        // path, including tenant-specific role assignments.
        $rolesQuery = $user->roles();

        if ($tenant = Filament::getTenant()) {
            $rolesQuery->whereBelongsTo($tenant);
        }

        $roles = $rolesQuery->get();

        // The configured owner role is the explicit superadministrator and
        // must not be locked out when a resource introduces a new permission.
        $ownerRole = trim((string) config('hexa.super_admin_role', 'Owner'));

        if ($ownerRole !== '' && $roles->contains(
            fn ($role) => Str::lower(trim((string) $role->name)) === Str::lower($ownerRole)
        )) {
            return true;
        }

        // Preserve HexaLite's existing behavior for accounts with no roles.
        if ($roles->isEmpty()) {
            return true;
        }

        $grantedPermissions = $roles
            ->flatMap(fn ($role) => is_array($role->access) ? Arr::flatten($role->access) : [])
            ->filter(fn ($permission) => is_string($permission))
            ->values()
            ->all();

        $requestedPermissions = Arr::flatten(is_array($permissions) ? $permissions : [$permissions]);
        $requestedPermissions = array_values(array_filter($requestedPermissions, 'is_string'));

        return ! empty(array_intersect($grantedPermissions, $requestedPermissions));
    }
}
