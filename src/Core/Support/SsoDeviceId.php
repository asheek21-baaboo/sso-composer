<?php

namespace Company\Sso\Core\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

final class SsoDeviceId
{
    public const string COOKIE_NAME = 'sso_device_id';

    public static function resolve(Request $request): string
    {
        $fromCookie = $request->cookie(self::COOKIE_NAME);

        if (is_string($fromCookie) && self::isValidUuid($fromCookie)) {
            return $fromCookie;
        }

        return (string) Str::uuid();
    }

    public static function makeCookie(string $deviceId): Cookie
    {
        return cookie(
            name: self::COOKIE_NAME,
            value: $deviceId,
            minutes: 60 * 24 * 365 * 2,
            path: '/',
            secure: config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public static function isValidUuid(string $value): bool
    {
        return Str::isUuid($value);
    }
}
