<?php

namespace Company\Sso\Server\Actions;

use Company\Sso\Core\Contracts\OAuthAuditLogger;
use Company\Sso\Core\Contracts\OAuthAuthorizationCodeStore;
use Company\Sso\Core\Data\OAuthProject;
use Company\Sso\Core\Data\OAuthUser;
use Company\Sso\Core\Enums\AuthAuditAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class IssueOAuthAuthorizationCodeRedirect
{
    public function __construct(
        private readonly OAuthAuthorizationCodeStore $authorizationCodeStore,
        private readonly OAuthAuditLogger $auditLogger,
    ) {}

    public function execute(
        OAuthUser $user,
        OAuthProject $project,
        string $redirectUri,
        string $deviceId,
        Request $request,
    ): string {
        $plain = Str::random(48);
        $hash = hash('sha256', $plain);

        $ttl = (int) config('sso.authorization_code_ttl_seconds', 60);
        $expiresAt = now()->addSeconds($ttl);

        $this->authorizationCodeStore->create(
            userId: $user->id,
            projectId: $project->id,
            deviceId: $deviceId,
            redirectUriSnapshot: $redirectUri,
            codeHash: $hash,
            expiresAt: $expiresAt,
        );

        $this->auditLogger->log(
            AuthAuditAction::Login,
            $user->id,
            $project->id,
            (string) $request->ip(),
            substr((string) $request->userAgent(), 0, 4096),
            null,
        );

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri.$separator.http_build_query(['code' => $plain]);
    }
}
