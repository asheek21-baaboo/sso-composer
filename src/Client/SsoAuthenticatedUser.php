<?php

namespace Company\Sso\Client;

use stdClass;

final readonly class SsoAuthenticatedUser
{
    public function __construct(private stdClass $claims) {}

    public static function fromClaims(stdClass $claims): self
    {
        return new self($claims);
    }

    public function id(): int
    {
        return (int) ($this->claims->sub ?? 0);
    }

    public function email(): string
    {
        return (string) ($this->claims->email ?? '');
    }

    public function projectRole(): string
    {
        return (string) ($this->claims->project_role ?? '');
    }

    public function globalRole(): string
    {
        return (string) ($this->claims->global_role ?? '');
    }

    public function createUser(): bool
    {
        return (bool) ($this->claims->createUser ?? false);
    }

    public function claims(): stdClass
    {
        return $this->claims;
    }
}
