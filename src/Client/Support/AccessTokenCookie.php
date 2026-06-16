<?php

namespace Company\Sso\Client\Support;

use Symfony\Component\HttpFoundation\Cookie;

final class AccessTokenCookie
{
    public static function name(): string
    {
        return (string) config('sso.access_token_cookie', 'sso_access_token');
    }

    public static function make(string $token, int $expiresInSeconds): Cookie
    {
        return cookie(
            name: self::name(),
            value: $token,
            minutes: (int) ceil($expiresInSeconds / 60),
            path: '/',
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public static function forget(): Cookie
    {
        return cookie()->forget(self::name());
    }
}
