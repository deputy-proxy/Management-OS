<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

final class RevokeMcpPrincipalTokens extends Command
{
    protected $signature = 'mcp:revoke-principal-tokens';

    protected $description = 'Revoke every Passport access and refresh token issued to the MCP AI principal.';

    public function handle(): int
    {
        $email = config('mcp.ai_principal_email');

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('Set MCP_AI_PRINCIPAL_EMAIL to a valid address before revoking tokens.');

            return self::FAILURE;
        }

        $principal = User::query()->where('email', $email)->first();

        if ($principal === null) {
            $this->components->error('The configured MCP AI principal does not exist.');

            return self::FAILURE;
        }

        [$accessTokensRevoked, $refreshTokensRevoked] = DB::transaction(function () use ($principal): array {
            $tokenIds = Passport::token()::query()
                ->where('user_id', $principal->getKey())
                ->pluck('id');

            $refreshTokensRevoked = Passport::refreshToken()::query()
                ->whereIn('access_token_id', $tokenIds)
                ->where('revoked', false)
                ->update(['revoked' => true]);

            $accessTokensRevoked = Passport::token()::query()
                ->whereIn('id', $tokenIds)
                ->where('revoked', false)
                ->update(['revoked' => true]);

            return [$accessTokensRevoked, $refreshTokensRevoked];
        });

        $this->components->info("Revoked {$accessTokensRevoked} access token(s) and {$refreshTokensRevoked} refresh token(s) for {$email}.");

        return self::SUCCESS;
    }
}
