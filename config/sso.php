<?php

return [
    'idp_url' => rtrim((string) env('SSO_BASE_URL', ''), '/'),

    'project_id' => env('SSO_PROJECT_ID'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'app_url' => rtrim((string) env('APP_URL', ''), '/'),
    'redirect_path' => '/oauth/callback',

    'access_token_cookie' => 'sso_access_token',

    'jwks_cache_seconds' => (int) env('SSO_JWKS_CACHE_SECONDS', 3600),

    'routes' => [
        'register' => true,
        'prefix' => '',
        'middleware' => ['web'],
        'login_route_name' => 'sso.login',
        'callback_route_name' => 'sso.callback',
    ],

    'home_route' => env('SSO_HOME_ROUTE', 'home'),
];
