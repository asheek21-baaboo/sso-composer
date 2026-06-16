<?php

namespace Company\Sso\Server\Actions;

use Company\Sso\Core\Contracts\OAuthSessionStore;
use Company\Sso\Core\Contracts\OAuthUserResolver;
use Company\Sso\Core\Jwt\AccessTokenVerifier;
use Company\Sso\Core\Jwt\JwtIssuer;
use Illuminate\Http\Exceptions\HttpResponseException;

final class TouchOAuthHeartbeat
{
    public function __construct(
        private readonly AccessTokenVerifier $accessTokenVerifier,
        private readonly OAuthUserResolver $userResolver,
        private readonly OAuthSessionStore $sessionStore,
    ) {}

    public function execute(string $jwt): void
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

        $activity = $this->sessionStore->findOpenByJti($jti);

        if ($activity === null || (string) $activity['user_id'] !== (string) $user->id) {
            $this->deny();
        }

        $this->sessionStore->touch($activity['id']);
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
