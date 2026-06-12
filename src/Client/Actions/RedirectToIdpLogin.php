<?php

namespace Company\Sso\Client\Actions;

use Company\Sso\Core\Support\OAuthUrls;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

final class RedirectToIdpLogin
{
    public function execute(?string $prompt = null): RedirectResponse
    {
        $idpUrl = (string) config('sso.idp_url');
        $projectId = (string) config('sso.project_id');
        $clientId = (string) config('sso.client_id');
        $clientSecret = (string) config('sso.client_secret');
        $appUrl = (string) config('sso.app_url');

        if ($idpUrl === '' || $projectId === '' || $clientId === '' || $clientSecret === '' || $appUrl === '') {
            throw new RuntimeException('SSO client configuration is incomplete.');
        }

        $redirectUri = OAuthUrls::redirectUri($appUrl, (string) config('sso.redirect_path', '/oauth/callback'));
        $url = OAuthUrls::authorize($idpUrl, $projectId, $redirectUri, $prompt);

        return redirect()->away($url);
    }
}
