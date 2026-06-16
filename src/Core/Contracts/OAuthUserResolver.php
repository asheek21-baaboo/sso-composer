<?php

namespace Company\Sso\Core\Contracts;

use Company\Sso\Core\Data\OAuthProject;
use Company\Sso\Core\Data\OAuthUser;

interface OAuthUserResolver
{
    public function findById(int|string $id): ?OAuthUser;

    public function mayAccessProject(OAuthUser $user, OAuthProject $project): bool;

    public function resolveProjectRole(OAuthUser $user, OAuthProject $project): string;
}
