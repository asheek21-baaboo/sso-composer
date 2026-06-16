<?php

namespace Company\Sso\Support;

final class OAuthUrls
{
    public static function authorize(string $idpUrl, string $projectSlug, string $redirectUri, ?string $prompt = null): string
    {
        $params = [
            'project_id' => $projectSlug,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
        ];

        if ($prompt === 'login') {
            $params['prompt'] = 'login';
        }

        return rtrim($idpUrl, '/').'/oauth/authorize?'.http_build_query($params);
    }

    public static function token(string $idpUrl): string
    {
        return rtrim($idpUrl, '/').'/oauth/token';
    }

    public static function jwks(string $idpUrl): string
    {
        return rtrim($idpUrl, '/').'/jwks';
    }

    public static function heartbeat(string $idpUrl): string
    {
        return rtrim($idpUrl, '/').'/oauth/heartbeat';
    }

    public static function sessionEnd(string $idpUrl): string
    {
        return rtrim($idpUrl, '/').'/oauth/session/end';
    }

    public static function redirectUri(string $appUrl, string $path = '/oauth/callback'): string
    {
        return rtrim($appUrl, '/').$path;
    }
}
