<?php

namespace Company\Sso\Server\Actions;

use Company\Sso\Core\Contracts\OAuthAuditLogger;
use Company\Sso\Core\Contracts\OAuthSessionStore;
use Company\Sso\Core\Contracts\OAuthUserResolver;
use Company\Sso\Core\Enums\AuthAuditAction;
use Company\Sso\Core\Jwt\AccessTokenVerifier;
use Company\Sso\Core\Jwt\JwtIssuer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

final class EndOAuthAccessSession
{
    public function __construct(
        private readonly AccessTokenVerifier $accessTokenVerifier,
        private readonly OAuthUserResolver $userResolver,
        private readonly OAuthSessionStore $sessionStore,
        private readonly OAuthAuditLogger $auditLogger,
    ) {}

    public function execute(string $jwt, Request $request): void
    {
        try {
            $claims = $this->accessTokenVerifier->decode($jwt);
        } catch (\Throwable) {
            $this->deny();
        }

        $iss = isset($claims->iss) ? (string) $claims->iss : '';
        $sub = isset($claims->sub) ? (string) $claims->sub : '';
        $jti = isset($claims->jti) ? (string) $claims->jti : '';

        if ($sub === '' || $jti === '') {
            $this->deny();
        }

        if (! JwtIssuer::matches($iss)) {
            $this->deny();
        }

        $user = $this->userResolver->findById($sub);

        if ($user === null || ! $user->isActive) {
            $this->deny();
        }

        $activity = $this->sessionStore->findByJti($jti);

        if ($activity === null || (string) $activity['user_id'] !== (string) $user->id) {
            $this->deny();
        }

        if ($activity['session_end'] !== null) {
            return;
        }

        $end = now();
        $this->sessionStore->close($activity['id'], $end);

        $this->auditLogger->log(
            AuthAuditAction::TokenRevoked,
            $user->id,
            $activity['project_id'],
            (string) $request->ip(),
            substr((string) $request->userAgent(), 0, 4096),
            ['jti' => $jti],
        );
    }

    /**
     * @return never
     */
    private function deny(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Unauthorized.',
        ], 401));
    }
}
