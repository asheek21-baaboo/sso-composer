<?php

use Company\Sso\Client\Support\AccessTokenCookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses()->group('client');

beforeEach(function (): void {
    config([
        'sso.mode' => 'client',
        'sso.idp_url' => 'https://idp.test',
        'sso.project_id' => 'my-app',
        'sso.client_id' => (string) \Illuminate\Support\Str::uuid(),
        'sso.client_secret' => 'secret',
        'sso.app_url' => 'https://app.test',
        'sso.home_route' => 'home',
    ]);

    Route::get('/home', fn () => 'home')->name('home');
});

test('callback with valid code sets cookie and redirects home', function (): void {
    Http::fake([
        'https://idp.test/oauth/token' => Http::response([
            'access_token' => 'jwt-token-value',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
    ]);

    $response = $this->get('/oauth/callback?code=valid-code');

    $response->assertRedirect(route('home'));
    $response->assertCookie(AccessTokenCookie::name(), 'jwt-token-value');
});

test('callback without code redirects to login with error', function (): void {
    $response = $this->get('/oauth/callback');

    $response->assertRedirect(route('sso.login'));
    $response->assertSessionHas('error');
});

test('token exchange failure redirects with error', function (): void {
    Http::fake([
        'https://idp.test/oauth/token' => Http::response(['message' => 'Invalid code.'], 422),
    ]);

    $response = $this->get('/oauth/callback?code=bad-code');

    $response->assertRedirect(route('sso.login'));
    $response->assertSessionHas('error', 'Invalid code.');
});
