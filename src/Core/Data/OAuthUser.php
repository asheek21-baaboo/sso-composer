<?php

namespace Company\Sso\Core\Data;

final readonly class OAuthUser
{
    public function __construct(
        public int|string $id,
        public string $email,
        public string $globalRole,
        public bool $isActive,
        public string $projectRole = '',
    ) {}
}
