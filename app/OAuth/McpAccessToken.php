<?php

namespace App\OAuth;

use DateTimeImmutable;
use Laravel\Passport\Bridge\AccessToken as PassportAccessToken;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\CryptKeyInterface;
use RuntimeException;

/** Passport access tokens bound to the MCP resource server audience. */
final class McpAccessToken extends PassportAccessToken
{
    private ?CryptKeyInterface $mcpPrivateKey = null;

    public function setPrivateKey(CryptKeyInterface $privateKey): void
    {
        $this->mcpPrivateKey = $privateKey;
    }

    public function toString(): string
    {
        $resource = request()->attributes->get('mcp_resource');

        if (! is_string($resource) || $resource === '') {
            return parent::toString();
        }

        if (! $this->mcpPrivateKey instanceof CryptKeyInterface) {
            throw new RuntimeException('OAuth signing key was not configured.');
        }

        $keyContents = $this->mcpPrivateKey->getKeyContents();

        if ($keyContents === '') {
            throw new RuntimeException('OAuth signing key is empty.');
        }

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($keyContents, $this->mcpPrivateKey->getPassPhrase() ?? ''),
            InMemory::plainText('empty', 'empty'),
        );

        return $configuration->builder()
            ->issuedBy((string) (config('mcp.authorization_server') ?? url('/')))
            ->permittedFor($this->getClient()->getIdentifier())
            ->permittedFor($resource)
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable)
            ->canOnlyBeUsedAfter(new DateTimeImmutable)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getUserIdentifier() ?? $this->getClient()->getIdentifier())
            ->withClaim('client_id', $this->getClient()->getIdentifier())
            ->withClaim('scopes', array_map(static fn ($scope): string => $scope->getIdentifier(), $this->getScopes()))
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }
}
