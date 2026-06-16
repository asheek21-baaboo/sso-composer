<?php

namespace Company\Sso\Server\Concerns;

use Company\Sso\Core\Support\OAuthUrls;
use Company\Sso\Server\Contracts\OAuthClientProject;

trait InteractsWithOAuthClient
{
    public function redirectUri(): string
    {
        /** @var OAuthClientProject $this */
        return OAuthUrls::redirectUri($this->oauthClientAppUrl());
    }

    public function authorizeUri(?string $idpUrl = null): string
    {
        /** @var OAuthClientProject $this */
        return OAuthUrls::authorize(
            $idpUrl ?? (string) config('app.url'),
            $this->oauthProjectSlug(),
            $this->redirectUri(),
        );
    }

    public function ssoClientConfigured(): bool
    {
        /** @var OAuthClientProject $this */
        return filled($this->oauthClientId())
            && filled($this->oauthClientSecretHash())
            && filled($this->oauthClientAppUrl());
    }

    public function canVisit(): bool
    {
        return $this->ssoClientConfigured();
    }
}
