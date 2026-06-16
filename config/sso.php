<?php

return [
    'mode' => env('SSO_MODE', 'client'),
    'idp_url' => rtrim((string) env('SSO_BASE_URL', ''), '/'),
    
    'project_id' => env('SSO_PROJECT_ID'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'app_url' => rtrim((string) env('APP_URL', ''), '/'),
    'redirect_path' => '/oauth/callback',
    
    'access_token_cookie' => 'sso_access_token',
    
    'issuer' => env('JWT_ISSUER', env('APP_URL')),
    'key_id' => env('JWT_KEY_ID', 'company-sso-1'),
    'ttl_seconds' => (int) env('JWT_TTL_SECONDS', 36_000),
    'authorization_code_ttl_seconds' => (int) env('JWT_AUTHORIZATION_CODE_TTL', 60),
    'private_key_pem' => env('JWT_PRIVATE_KEY_PEM'),
    'public_key_pem' => env('JWT_PUBLIC_KEY_PEM'),
    'private_key_path' => env('JWT_PRIVATE_KEY_PATH'),
    'public_key_path' => env('JWT_PUBLIC_KEY_PATH'),
    
    'jwks_cache_seconds' => (int) env('SSO_JWKS_CACHE_SECONDS', 3600),
    
    'routes' => [
        'register' => true,
        'server_prefix' => '',
        'client_prefix' => '',
        'server_middleware' => ['api'],
        'client_middleware' => ['web'],
        'login_route_name' => 'sso.login',
        'callback_route_name' => 'sso.callback',
    ],
    
    'home_route' => env('SSO_HOME_ROUTE', 'home'),
];
