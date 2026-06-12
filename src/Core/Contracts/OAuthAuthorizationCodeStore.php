<?php

namespace Company\Sso\Core\Contracts;

interface OAuthAuthorizationCodeStore
{
    /**
     * @return array{id: int|string}
     */
    public function create(
        int|string $userId,
        int|string $projectId,
        string $deviceId,
        string $redirectUriSnapshot,
        string $codeHash,
        \DateTimeInterface $expiresAt,
    ): array;

    /**
     * @return array{
     *     id: int|string,
     *     user_id: int|string,
     *     project_id: int|string,
     *     device_id: string|null,
     *     redirect_uri_snapshot: string,
     *     expires_at: \DateTimeInterface
     * }|null
     */
    public function findByCodeHash(string $codeHash): ?array;

    public function delete(int|string $id): void;
}
