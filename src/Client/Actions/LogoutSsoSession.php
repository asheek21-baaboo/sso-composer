<?php

namespace Company\Sso\Client\Actions;

use Company\Sso\Client\Support\AccessTokenCookie;
use Company\Sso\Core\Support\OAuthUrls;
use Company\Sso\Core\Support\SsoDeviceId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class LogoutSsoSession
{
    public function execute(Request $request): RedirectResponse
    {
        $token = $request->cookie(AccessTokenCookie::name());
        $idpUrl = (string) config('sso.idp_url');

        if (is_string($token) && $token !== '' && $idpUrl !== '') {
            try {
                Http::withToken($token)->post(OAuthUrls::sessionEnd($idpUrl));
            } catch (\Throwable) {
                // Ignore remote failures during logout.
            }
        }

        $redirect = redirect()->route((string) config('sso.routes.login_route_name', 'sso.login'));

        return $redirect
            ->withCookie(AccessTokenCookie::forget())
            ->withCookie(cookie()->forget(SsoDeviceId::COOKIE_NAME));
    }
}
