<?php

use Company\Sso\Client\Support\AccessTokenCookie;
use Company\Sso\Facades\SsoUser;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses()->group('client');

beforeEach(function (): void {
    $privatePem = (string) file_get_contents(__DIR__.'/../../Fixtures/jwt/rs256-private.pem');
    $publicPem = (string) file_get_contents(__DIR__.'/../../Fixtures/jwt/rs256-public.pem');

    config([
        'sso.mode' => 'client',
        'sso.idp_url' => 'https://idp.test',
        'sso.project_id' => 'my-app',
        'sso.client_id' => (string) \Illuminate\Support\Str::uuid(),
        'sso.client_secret' => 'secret',
        'sso.app_url' => 'https://app.test',
        'sso.jwks_cache_seconds' => 3600,
        'sso.private_key_pem' => $privatePem,
        'sso.public_key_pem' => $publicPem,
        'sso.key_id' => 'test-kid',
    ]);

    $loader = \Company\Sso\Core\Jwt\JwtKeyLoader::fromApplicationConfig();
    $jwk = $loader->publicJwk();

    Cache::flush();

    Http::fake([
        'https://idp.test/jwks' => Http::response(['keys' => [$jwk]], 200),
    ]);

    $this->token = JWT::encode(
        [
            'iss' => 'https://idp.test',
            'aud' => 'my-app',
            'sub' => '99',
            'email' => 'middleware@company.com',
            'global_role' => 'staff',
            'project_id' => 'my-app',
            'project_role' => 'viewer',
            'createUser' => true,
            'iat' => time(),
            'exp' => time() + 3600,
            'jti' => (string) \Illuminate\Support\Str::uuid(),
        ],
        $privatePem,
        'RS256',
        'test-kid',
        ['typ' => 'JWT'],
    );

    Route::middleware('sso.auth')->get('/protected', function () {
        return SsoUser::email();
    })->name('protected');
});

test('no cookie redirects to login', function (): void {
    $this->get('/protected')->assertRedirect(route('sso.login'));
});

test('valid cookie passes through and SsoUser email works', function (): void {
    $response = $this->withUnencryptedCookie(AccessTokenCookie::name(), $this->token)
        ->get('/protected');

    $response->assertOk();
    expect($response->getContent())->toBe('middleware@company.com');
});
