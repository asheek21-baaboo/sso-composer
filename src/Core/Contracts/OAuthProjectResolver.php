<?php

namespace Company\Sso\Core\Contracts;

use Company\Sso\Core\Data\OAuthProject;

interface OAuthProjectResolver
{
    public function findBySlug(string $slug): ?OAuthProject;

    public function findById(int|string $id): ?OAuthProject;
}
