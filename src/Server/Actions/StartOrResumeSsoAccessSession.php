<?php

namespace Company\Sso\Server\Actions;

use Company\Sso\Core\Contracts\OAuthSessionStore;
use Company\Sso\Core\Contracts\OAuthUserResolver;
use Company\Sso\Core\Data\OAuthProject;
use Company\Sso\Core\Data\OAuthUser;
use Company\Sso\Core\Jwt\AccessTokenIssuer;
use Illuminate\Http\Request;

final class StartOrResumeSsoAccessSession
{
    public function __construct(
        private readonly AccessTokenIssuer $accessTokenIssuer,
        private readonly OAuthUserResolver $userResolver,
        private readonly OAuthSessionStore $sessionStore,
        private readonly CloseUserActivitySession $closeUserActivitySession,
    ) {}

    /**
     * @return array{access_token: string, expires_in: int, token_type: string}
     */
    public function execute(OAuthUser $user, OAuthProject $project, string $deviceId, Request $request): array
    {
        $projectRole = $this->userResolver->resolveProjectRole($user, $project);
        $ttl = max(1, (int) config('sso.ttl_seconds', 36_000));

        $existing = $this->sessionStore->findOpenSession($user->id, $project->id, $deviceId);

        if ($existing !== null) {
            $sessionStart = $existing['session_start'];
            $expiresAt = \DateTimeImmutable::createFromInterface($sessionStart)->modify("+{$ttl} seconds");

            if ($expiresAt > \DateTimeImmutable::createFromInterface(now()) && filled($existing['jti'])) {
                return $this->resume($user, $project, $projectRole, $existing, $expiresAt, $request);
            }

            $this->closeUserActivitySession->execute($existing['id'], $expiresAt);
        }

        $this->sessionStore->closeAllOpenForDevice($user->id, $project->id, $deviceId);

        return $this->startNew($user, $project, $projectRole, $deviceId, $request);
    }

    /**
     * @param  array{id: int|string, jti: string, session_start: \DateTimeInterface}  $activity
     * @return array{access_token: string, expires_in: int, token_type: string}
     */
    private function resume(
        OAuthUser $user,
        OAuthProject $project,
        string $projectRole,
        array $activity,
        \DateTimeInterface $expiresAt,
        Request $request,
    ): array {
        $issued = $this->accessTokenIssuer->issue(
            $user,
            $project,
            $projectRole,
            jti: (string) $activity['jti'],
            issuedAtUnix: $activity['session_start']->getTimestamp(),
            expiresAtUnix: $expiresAt->getTimestamp(),
        );

        $this->sessionStore->touch($activity['id']);
        $this->sessionStore->recordProjectVisit($user->id, $project->id, now());

        return $this->tokenResponse($issued['token'], $issued['expires_at_unix']);
    }

    /**
     * @return array{access_token: string, expires_in: int, token_type: string}
     */
    private function startNew(
        OAuthUser $user,
        OAuthProject $project,
        string $projectRole,
        string $deviceId,
        Request $request,
    ): array {
        $issued = $this->accessTokenIssuer->issue($user, $project, $projectRole);
        $now = now();

        $this->sessionStore->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'device_id' => $deviceId,
            'jti' => $issued['jti'],
            'session_start' => $now,
            'last_seen_at' => $now,
            'ip' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 4096),
        ]);

        $this->sessionStore->recordProjectVisit($user->id, $project->id, $now);

        return $this->tokenResponse($issued['token'], $issued['expires_at_unix']);
    }

    /**
     * @return array{access_token: string, expires_in: int, token_type: string}
     */
    private function tokenResponse(string $token, int $expiresAtUnix): array
    {
        $remaining = $expiresAtUnix - time();

        return [
            'access_token' => $token,
            'expires_in' => max(0, $remaining),
            'token_type' => 'Bearer',
        ];
    }
}
