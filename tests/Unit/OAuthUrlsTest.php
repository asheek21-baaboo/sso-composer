<?php

test('oauth urls are built correctly', function (): void {
    expect(\Company\Sso\Support\OAuthUrls::authorize('https://idp.test/', 'my-app', 'https://app.test/oauth/callback'))
        ->toBe('https://idp.test/oauth/authorize?project_id=my-app&redirect_uri=https%3A%2F%2Fapp.test%2Foauth%2Fcallback&response_type=code');

    expect(\Company\Sso\Support\OAuthUrls::authorize('https://idp.test', 'my-app', 'https://app.test/oauth/callback', 'login'))
        ->toContain('prompt=login');

    expect(\Company\Sso\Support\OAuthUrls::token('https://idp.test/'))->toBe('https://idp.test/oauth/token');
    expect(\Company\Sso\Support\OAuthUrls::jwks('https://idp.test'))->toBe('https://idp.test/jwks');
    expect(\Company\Sso\Support\OAuthUrls::heartbeat('https://idp.test'))->toBe('https://idp.test/oauth/heartbeat');
    expect(\Company\Sso\Support\OAuthUrls::sessionEnd('https://idp.test'))->toBe('https://idp.test/oauth/session/end');
    expect(\Company\Sso\Support\OAuthUrls::redirectUri('https://app.test/', '/oauth/callback'))->toBe('https://app.test/oauth/callback');
});
