<?php

namespace Company\Sso\Server\Actions;

use Company\Sso\Core\Contracts\OAuthSessionStore;

final class CloseUserActivitySession
{
    public function __construct(private readonly OAuthSessionStore $sessionStore) {}

    public function execute(int|string $sessionId, \DateTimeInterface $endAt): void
    {
        $this->sessionStore->close($sessionId, $endAt);
    }
}
