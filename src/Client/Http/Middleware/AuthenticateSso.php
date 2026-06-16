<?php

namespace Company\Sso\Client\Http\Middleware;

use Closure;
use Company\Sso\Client\SsoAuthenticatedUser;
use Company\Sso\Client\Support\AccessTokenCookie;
use Company\Sso\Core\Jwt\RemoteJwksVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateSso
{
    public function __construct(private readonly RemoteJwksVerifier $jwksVerifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(AccessTokenCookie::name());

        if (! is_string($token) || $token === '') {
            return redirect()->route((string) config('sso.routes.login_route_name', 'sso.login'));
        }

        try {
            $claims = $this->jwksVerifier->decode($token);
        } catch (\Throwable) {
            return redirect()
                ->route((string) config('sso.routes.login_route_name', 'sso.login'))
                ->withCookie(AccessTokenCookie::forget());
        }

        $user = SsoAuthenticatedUser::fromClaims($claims);
        $request->attributes->set('sso.user', $user);
        app()->instance(SsoAuthenticatedUser::class, $user);

        return $next($request);
    }
}
