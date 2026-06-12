<?php

namespace Company\Sso\Core\Data;

final readonly class OAuthProject
{
    public function __construct(
        public int|string $id,
        public string $slug,
        public string $url,
        public string $clientId,
        public string $clientSecretHash,
        public bool $isActive,
        public bool $ssoProvisionsUsers,
    ) {}

    public function redirectUri(): string
    {
        return rtrim($this->url, '/').'/oauth/callback';
    }

    public function ssoClientConfigured(): bool
    {
        return $this->clientId !== ''
            && $this->clientSecretHash !== ''
            && $this->url !== '';
    }

    public function matchesClientId(string $clientId): bool
    {
        return $this->clientId === $clientId;
    }
}
