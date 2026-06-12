<?php

namespace Company\Sso\Core\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

final class AccessTokenVerifier
{
    public function __construct(private readonly JwtKeyLoader $keys) {}

    public function decode(string $jwt): stdClass
    {
        return JWT::decode($jwt, new Key($this->keys->getPublicKeyPem(), 'RS256'));
    }
}
