<?php

namespace App\Console\Commands;

use App\Mcp\Authorization\McpToolPermissionRegistry;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProvisionMcpPrincipal extends Command
{
    protected $signature = 'mcp:provision-principal';

    protected $description = 'Create or verify the dedicated deny-by-default MCP AI principal.';

    public function handle(): int
    {
        $email = config('mcp.ai_principal_email');

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('Set MCP_AI_PRINCIPAL_EMAIL to a valid address before provisioning.');

            return self::FAILURE;
        }

        $assistantRoles = HexaRole::query()
            ->where('name', 'Assistant')
            ->where('guard', hexa()->guard())
            ->whereNull('team_id')
            ->get();

        if ($assistantRoles->count() !== 1) {
            $this->components->error('Create exactly one global Assistant role for the configured guard before provisioning the MCP principal.');

            return self::FAILURE;
        }

        $assistantRole = $assistantRoles->sole();
        $permissions = collect(Arr::flatten($assistantRole->access ?? []))
            ->filter(fn ($permission): bool => is_string($permission) && trim($permission) !== '');

        $allowedPermissions = app(McpToolPermissionRegistry::class)->allowedDomainPermissions();
        $unmappedPermissions = $permissions->reject(
            fn ($permission): bool => in_array($permission, $allowedPermissions, true)
        );

        if ($unmappedPermissions->isNotEmpty()) {
            $this->components->error('The Assistant role contains domain permissions that are not granted by the MCP permission matrix.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($email, $assistantRole): int {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => 'AI Assistant',
                    'email' => $email,
                    'password' => Str::random(96),
                ]);
                $user->roles()->attach($assistantRole->getKey());

                $this->components->info("Created AI Assistant principal (user ID {$user->getKey()}) with only MCP-mapped Assistant permissions.");

                return self::SUCCESS;
            }

            if ($user->name !== 'AI Assistant') {
                $this->components->warn('The configured email already belongs to an account with a different name; no account data was changed.');

                return self::FAILURE;
            }

            $roles = $user->roles()->get();

            if ($roles->count() !== 1 || (string) $roles->sole()->getKey() !== (string) $assistantRole->getKey()) {
                $this->components->error('The configured MCP principal must have exactly the permissionless Assistant role and no other roles.');

                return self::FAILURE;
            }

            if ($user->canAccessPanel(Filament::getCurrentPanel() ?? new Panel)) {
                $this->components->error('The configured MCP principal unexpectedly has Filament panel access; refusing to continue.');

                return self::FAILURE;
            }

            $this->components->info("Verified existing AI Assistant principal (user ID {$user->getKey()}) with only MCP-mapped Assistant permissions and no panel access.");

            return self::SUCCESS;
        });
    }
}
