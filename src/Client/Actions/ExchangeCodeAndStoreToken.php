<?php

namespace Company\Sso\Client\Actions;

use Company\Sso\Client\Support\AccessTokenCookie;
use Company\Sso\Core\Support\OAuthUrls;
use Company\Sso\Core\Support\SsoDeviceId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class ExchangeCodeAndStoreToken
{
    public function execute(string $code, Request $request): RedirectResponse
    {
        $idpUrl = (string) config('sso.base_url');
        $redirectUri = OAuthUrls::redirectUri(
            (string) config('sso.app_url'),
            (string) config('sso.redirect_path', '/oauth/callback'),
        );

        $response = Http::asJson()->post(OAuthUrls::token($idpUrl), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => (string) config('sso.client_id'),
            'client_secret' => (string) config('sso.client_secret'),
        ]);

        if (! $response->successful()) {
            return redirect()
                ->route((string) config('sso.routes.login_route_name', 'sso.login'))
                ->with('error', $response->json('message') ?? 'Token exchange failed.');
        }

        /** @var string $token */
        $token = (string) $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        $redirect = redirect()->route((string) config('sso.home_route', 'home'));
        $redirect->withCookie(AccessTokenCookie::make($token, $expiresIn));

        $deviceId = $request->cookie(SsoDeviceId::COOKIE_NAME);
        if (! is_string($deviceId) || ! SsoDeviceId::isValidUuid($deviceId)) {
            $deviceId = SsoDeviceId::resolve($request);
            $redirect->withCookie(SsoDeviceId::makeCookie($deviceId));
        }

        return $redirect;
    }
}
