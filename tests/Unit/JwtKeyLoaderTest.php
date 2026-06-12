<?php

use Company\Sso\Core\Jwt\JwtKeyLoader;

test('jwt key loader reads pem config and exposes jwk', function (): void {
    $privatePem = (string) file_get_contents(__DIR__.'/../Fixtures/jwt/rs256-private.pem');
    $publicPem = (string) file_get_contents(__DIR__.'/../Fixtures/jwt/rs256-public.pem');

    config([
        'sso.private_key_pem' => $privatePem,
        'sso.public_key_pem' => $publicPem,
        'sso.key_id' => 'test-kid',
    ]);

    $loader = JwtKeyLoader::fromApplicationConfig();

    expect($loader->getPrivateKeyPem())->toBe($privatePem)
        ->and($loader->getPublicKeyPem())->toBe($publicPem);

    $jwk = $loader->publicJwk();

    expect($jwk)->toHaveKeys(['kty', 'kid', 'use', 'alg', 'n', 'e'])
        ->and($jwk['kid'])->toBe('test-kid')
        ->and($jwk['alg'])->toBe('RS256');
});
