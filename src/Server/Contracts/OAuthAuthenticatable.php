<?php

namespace Company\Sso\Server\Contracts;

interface OAuthAuthenticatable
{
    public function oauthUserId(): int|string;

    public function oauthEmail(): string;

    public function oauthGlobalRole(): string;

    public function oauthIsActive(): bool;

    public function mayAccessOAuthProject(OAuthClientProject $project): bool;

    public function oauthProjectRoleFor(OAuthClientProject $project): string;
}
