<?php

namespace Company\Sso\Tests\Feature\Server;

use Company\Sso\Core\Contracts\OAuthAuditLogger;
use Company\Sso\Core\Contracts\OAuthAuthorizationCodeStore;
use Company\Sso\Core\Contracts\OAuthProjectResolver;
use Company\Sso\Core\Contracts\OAuthSessionStore;
use Company\Sso\Core\Contracts\OAuthUserResolver;
use Company\Sso\Core\Data\OAuthProject;
use Company\Sso\Core\Data\OAuthUser;
use Company\Sso\Core\Enums\AuthAuditAction;

final class InMemoryOAuthProjectResolver implements OAuthProjectResolver
{
  /** @var array<int|string, OAuthProject> */
    public array $projects = [];

    public function findBySlug(string $slug): ?OAuthProject
    {
        foreach ($this->projects as $project) {
            if ($project->slug === $slug) {
                return $project;
            }
        }

        return null;
    }

    public function findById(int|string $id): ?OAuthProject
    {
        return $this->projects[$id] ?? null;
    }
}

final class InMemoryOAuthUserResolver implements OAuthUserResolver
{
  /** @var array<int|string, OAuthUser> */
    public array $users = [];

  /** @var array<string, bool> */
    public array $access = [];

    public function findById(int|string $id): ?OAuthUser
    {
        return $this->users[$id] ?? null;
    }

    public function mayAccessProject(OAuthUser $user, OAuthProject $project): bool
    {
        return $this->access[$user->id.'|'.$project->id] ?? false;
    }

    public function resolveProjectRole(OAuthUser $user, OAuthProject $project): string
    {
        return $user->projectRole !== '' ? $user->projectRole : 'viewer';
    }
}

final class InMemoryOAuthAuthorizationCodeStore implements OAuthAuthorizationCodeStore
{
  /** @var array<int, array<string, mixed>> */
    public array $codes = [];

    private int $nextId = 1;

    public function create(
        int|string $userId,
        int|string $projectId,
        string $deviceId,
        string $redirectUriSnapshot,
        string $codeHash,
        \DateTimeInterface $expiresAt,
    ): array {
        $id = $this->nextId++;
        $this->codes[$id] = [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => $projectId,
            'device_id' => $deviceId,
            'redirect_uri_snapshot' => $redirectUriSnapshot,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
        ];

        return ['id' => $id];
    }

    public function findByCodeHash(string $codeHash): ?array
    {
        foreach ($this->codes as $code) {
            if ($code['code_hash'] === $codeHash) {
                return $code;
            }
        }

        return null;
    }

    public function delete(int|string $id): void
    {
        unset($this->codes[$id]);
    }
}

final class InMemoryOAuthSessionStore implements OAuthSessionStore
{
  /** @var array<int, array<string, mixed>> */
    public array $sessions = [];

    private int $nextId = 1;

    public function findOpenSession(int|string $userId, int|string $projectId, string $deviceId): ?array
    {
        foreach ($this->sessions as $session) {
            if ($session['user_id'] === $userId
                && $session['project_id'] === $projectId
                && $session['device_id'] === $deviceId
                && $session['session_end'] === null) {
                return $session;
            }
        }

        return null;
    }

    public function create(array $data): void
    {
        $id = $this->nextId++;
        $this->sessions[$id] = array_merge($data, [
            'id' => $id,
            'session_end' => null,
        ]);
    }

    public function touch(int|string $sessionId): void
    {
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['last_seen_at'] = new \DateTimeImmutable;
        }
    }

    public function close(int|string $sessionId, \DateTimeInterface $endAt): void
    {
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['session_end'] = $endAt;
        }
    }

    public function closeAllOpenForDevice(int|string $userId, int|string $projectId, string $deviceId): void
    {
        foreach ($this->sessions as $id => $session) {
            if ($session['user_id'] === $userId
                && $session['project_id'] === $projectId
                && $session['device_id'] === $deviceId
                && $session['session_end'] === null) {
                $this->close($id, new \DateTimeImmutable);
            }
        }
    }

    public function findOpenByJti(string $jti): ?array
    {
        foreach ($this->sessions as $session) {
            if ($session['jti'] === $jti && $session['session_end'] === null) {
                return $session;
            }
        }

        return null;
    }

    public function findByJti(string $jti): ?array
    {
        foreach ($this->sessions as $session) {
            if ($session['jti'] === $jti) {
                return $session;
            }
        }

        return null;
    }

    public function recordProjectVisit(int|string $userId, int|string $projectId, \DateTimeInterface $at): void {}
}

final class RecordingOAuthAuditLogger implements OAuthAuditLogger
{
  /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function log(
        AuthAuditAction $action,
        int|string|null $userId,
        int|string|null $projectId,
        ?string $ip,
        ?string $userAgent,
        ?array $meta,
    ): void {
        $this->entries[] = compact('action', 'userId', 'projectId', 'ip', 'userAgent', 'meta');
    }
}
