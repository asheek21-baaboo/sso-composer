<?php

namespace Company\Sso\Core\Jwt;

use Company\Sso\Core\Data\OAuthProject;
use Company\Sso\Core\Data\OAuthUser;
use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;

final class AccessTokenIssuer
{
    public function __construct(private readonly JwtKeyLoader $keys) {}

    /**
     * @return array{token: string, jti: string, expires_at_unix: int}
     */
    public function issue(
        OAuthUser $user,
        OAuthProject $project,
        string $projectRole,
        ?string $jti = null,
        ?int $issuedAtUnix = null,
        ?int $expiresAtUnix = null,
    ): array {
        $timestamp = JWT::$timestamp;
        $clockNow = is_int($timestamp) ? $timestamp : time();

        $ttl = (int) config('sso.ttl_seconds', 36_000);
        $now = $issuedAtUnix ?? $clockNow;
        $exp = $expiresAtUnix ?? ($now + $ttl);
        $jti = $jti ?? Uuid::uuid4()->toString();

        $payload = [
            'iss' => JwtIssuer::resolve(),
            'aud' => $project->slug,
            'sub' => (string) $user->id,
            'email' => $user->email,
            'global_role' => $user->globalRole,
            'project_id' => $project->slug,
            'project_role' => $projectRole,
            'createUser' => $project->ssoProvisionsUsers,
            'iat' => $now,
            'exp' => $exp,
            'jti' => $jti,
        ];

        $jwt = JWT::encode(
            $payload,
            $this->keys->getPrivateKeyPem(),
            'RS256',
            (string) config('sso.key_id'),
            ['typ' => 'JWT'],
        );

        return [
            'token' => $jwt,
            'jti' => $jti,
            'expires_at_unix' => $exp,
        ];
    }
}
