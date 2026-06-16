<?php

namespace Company\Sso\Core\Contracts;

use Company\Sso\Core\Enums\AuthAuditAction;

interface OAuthAuditLogger
{
    public function log(
        AuthAuditAction $action,
        int|string|null $userId,
        int|string|null $projectId,
        ?string $ip,
        ?string $userAgent,
        ?array $meta,
    ): void;
}
