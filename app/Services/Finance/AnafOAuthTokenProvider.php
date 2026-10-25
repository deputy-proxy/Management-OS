<?php

namespace App\Services\Finance;

use App\Models\InvoiceSubmissionOAuthCredential;
use App\Models\LegalEntity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AnafOAuthTokenProvider
{
    public function accessToken(LegalEntity $entity, string $environment): string
    {
        $settings = config('services.efactura.environments.'.$environment);
        if (! is_array($settings) || ! in_array($environment, ['test', 'production'], true)) {
            throw new RuntimeException('Unsupported ANAF e-Factura environment.');
        }
        $tokenUrl = (string) config('services.efactura.oauth_token_url');
        $tokenParts = parse_url($tokenUrl);
        if (! is_array($tokenParts) || ($tokenParts['scheme'] ?? null) !== 'https'
            || strtolower((string) ($tokenParts['host'] ?? '')) !== 'logincert.anaf.ro'
            || ($tokenParts['path'] ?? null) !== '/anaf-oauth2/v1/token'
            || isset($tokenParts['user']) || isset($tokenParts['pass'])) {
            throw new RuntimeException('The configured OAuth token URL is not the approved ANAF authorization endpoint.');
        }
        $clientId = $settings['client_id'] ?? null;
        $clientSecret = $settings['client_secret'] ?? null;
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException('ANAF OAuth credentials are not configured for the selected environment.');
        }

        return DB::transaction(function () use ($entity, $environment, $settings, $clientId, $clientSecret): string {
            $credential = InvoiceSubmissionOAuthCredential::query()
                ->where('legal_entity_id', $entity->getKey())
                ->where('environment', $environment)
                ->lockForUpdate()
                ->first();

            if ($credential?->access_token && $credential->expires_at !== null
                && Carbon::parse($credential->expires_at)->isAfter(now()->addSeconds(60))) {
                return $credential->access_token;
            }

            $refreshToken = $credential?->refresh_token ?: ($settings['refresh_token'] ?? null);
            if (! is_string($refreshToken) || $refreshToken === '') {
                throw new RuntimeException('ANAF OAuth refresh token is not configured for the selected environment.');
            }

            try {
                $response = Http::asForm()
                    ->acceptJson()
                    ->connectTimeout((int) config('services.efactura.connect_timeout_seconds', 5))
                    ->timeout((int) config('services.efactura.timeout_seconds', 30))
                    ->post((string) config('services.efactura.oauth_token_url'), [
                        'grant_type' => 'refresh_token',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'refresh_token' => $refreshToken,
                    ]);
            } catch (Throwable) {
                throw new RuntimeException('ANAF OAuth token refresh could not reach the authorization service. No token response was retained.');
            }

            $body = $response->json();
            if (! $response->successful() || ! is_array($body)
                || ! is_string($body['access_token'] ?? null) || $body['access_token'] === ''
                || ! is_numeric($body['expires_in'] ?? null) || (int) $body['expires_in'] < 1) {
                throw new RuntimeException('ANAF OAuth token refresh failed. Verify authorization and deployment secrets; response details are intentionally not logged.');
            }

            $credential ??= new InvoiceSubmissionOAuthCredential([
                'legal_entity_id' => $entity->getKey(),
                'environment' => $environment,
            ]);
            $credential->forceFill([
                'access_token' => $body['access_token'],
                'refresh_token' => is_string($body['refresh_token'] ?? null) && $body['refresh_token'] !== '' ? $body['refresh_token'] : $refreshToken,
                'expires_at' => now()->addSeconds((int) $body['expires_in']),
                'token_refreshed_at' => now(),
            ])->save();

            return $credential->access_token;
        });
    }
}
