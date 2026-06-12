<?php

namespace Company\Sso\Core\Contracts;

interface OAuthSessionStore
{
    /**
     * @return array{id: int|string, jti: string, session_start: \DateTimeInterface}|null
     */
    public function findOpenSession(int|string $userId, int|string $projectId, string $deviceId): ?array;

    /**
     * @param array{
     *     user_id: int|string,
     *     project_id: int|string,
     *     device_id: string,
     *     jti: string,
     *     session_start: \DateTimeInterface,
     *     last_seen_at: \DateTimeInterface,
     *     ip: string,
     *     user_agent: string
     * } $data
     */
    public function create(array $data): void;

    public function touch(int|string $sessionId): void;

    public function close(int|string $sessionId, \DateTimeInterface $endAt): void;

    public function closeAllOpenForDevice(int|string $userId, int|string $projectId, string $deviceId): void;

    /**
     * @return array{
     *     id: int|string,
     *     user_id: int|string,
     *     project_id: int|string,
     *     session_start: \DateTimeInterface,
     *     session_end: \DateTimeInterface|null
     * }|null
     */
    public function findOpenByJti(string $jti): ?array;

    /**
     * @return array{
     *     id: int|string,
     *     user_id: int|string,
     *     project_id: int|string,
     *     session_start: \DateTimeInterface,
     *     session_end: \DateTimeInterface|null
     * }|null
     */
    public function findByJti(string $jti): ?array;

    public function recordProjectVisit(int|string $userId, int|string $projectId, \DateTimeInterface $at): void;
}
