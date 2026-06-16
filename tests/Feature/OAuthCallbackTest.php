<?php

use Company\Sso\Support\AccessTokenCookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
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
