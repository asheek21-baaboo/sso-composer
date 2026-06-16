<?php

namespace Company\Sso\Server\Contracts;

interface OAuthClientProject
{
    public function oauthProjectSlug(): string;

    public function oauthClientAppUrl(): string;

    public function oauthClientId(): ?string;

    public function oauthClientSecretHash(): ?string;

    public function oauthProjectIsActive(): bool;

    public function oauthProvisionsUsers(): bool;
}
