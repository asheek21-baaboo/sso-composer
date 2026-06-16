<?php

use Company\Sso\Support\AccessTokenCookie;
use Company\Sso\Facades\SsoUser;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $privatePem = (string) file_get_contents(__DIR__.'/../Fixtures/jwt/rs256-private.pem');
    $publicPem = (string) file_get_contents(__DIR__.'/../Fixtures/jwt/rs256-public.pem');

    Cache::flush();

    Http::fake([
        'https://idp.test/jwks' => Http::response(['keys' => [publicJwkFromPem($publicPem)]], 200),
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

/**
 * @return array{kty: string, kid: string, use: string, alg: string, n: string, e: string}
 */
function publicJwkFromPem(string $publicPem, string $kid = 'test-kid'): array
{
    $resource = openssl_pkey_get_public($publicPem);

    if ($resource === false) {
        throw new RuntimeException('Invalid public key PEM.');
    }

    $details = openssl_pkey_get_details($resource);

    if (! is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
        throw new RuntimeException('Public key must be RSA.');
    }

    /** @var array{n: string, e: string} $rsa */
    $rsa = $details['rsa'];

    return [
        'kty' => 'RSA',
        'kid' => $kid,
        'use' => 'sig',
        'alg' => 'RS256',
        'n' => base64UrlIntegerFromBigEndianUnsigned($rsa['n']),
        'e' => base64UrlIntegerFromBigEndianUnsigned($rsa['e']),
    ];
}

function base64UrlIntegerFromBigEndianUnsigned(string $bytes): string
{
    $trimmed = $bytes;

    while (strlen($trimmed) > 0 && ord($trimmed[0]) === 0) {
        $trimmed = substr($trimmed, 1);
    }

    return rtrim(strtr(base64_encode($trimmed), '+/', '-_'), '=');
}
