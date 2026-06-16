<?php

namespace Company\Sso\Core;

use Company\Sso\Core\Contracts\OAuthAuditLogger;
use Company\Sso\Core\Enums\AuthAuditAction;

final class NullOAuthAuditLogger implements OAuthAuditLogger
{
    public function log(
        AuthAuditAction $action,
        int|string|null $userId,
        int|string|null $projectId,
        ?string $ip,
        ?string $userAgent,
        ?array $meta,
    ): void {}
}
