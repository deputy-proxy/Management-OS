<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
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

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => 'AI Assistant',
                'email' => $email,
                'password' => Str::random(96),
            ]);

            $this->components->info("Created AI Assistant principal (user ID {$user->getKey()}) with no roles or panel permissions.");

            return self::SUCCESS;
        }

        if ($user->roles()->exists()) {
            $this->components->error('The configured MCP principal already has HexaLite roles. Review and remove them explicitly before continuing.');

            return self::FAILURE;
        }

        if ($user->name !== 'AI Assistant') {
            $this->components->warn('The configured email already belongs to an account with a different name; no account data was changed.');

            return self::FAILURE;
        }

        $this->components->info("Verified existing AI Assistant principal (user ID {$user->getKey()}) with no roles or panel permissions.");

        return self::SUCCESS;
    }
}
