<?php

namespace Company\Sso\Core\Data;

final readonly class OAuthAuthorizeContext
{
    public function __construct(
        public OAuthProject $project,
        public string $redirectUri,
        public bool $forceInteractiveLogin,
    ) {}
}
