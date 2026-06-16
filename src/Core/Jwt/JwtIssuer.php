<?php

namespace Company\Sso\Core\Jwt;

final class JwtIssuer
{
    public static function resolve(): string
    {
        $configured = trim((string) config('sso.issuer', ''));

        if ($configured === '') {
            $configured = trim((string) config('app.url'));
        }

        return rtrim($configured, '/');
    }

    public static function matches(string $iss): bool
    {
        return rtrim(trim($iss), '/') === self::resolve();
    }
}
