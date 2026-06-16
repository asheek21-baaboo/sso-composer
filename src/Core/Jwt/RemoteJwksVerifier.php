<?php

namespace Company\Sso\Core\Jwt;

use Company\Sso\Core\Support\OAuthUrls;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

final class RemoteJwksVerifier
{
    public function decode(string $jwt): stdClass
    {
        $baseUrl = rtrim((string) config('sso.base_url'), '/');
        $projectId = (string) config('sso.project_id');

        if ($baseUrl === '' || $projectId === '') {
            throw new RuntimeException('SSO_BASE_URL and SSO_PROJECT_ID must be configured.');
        }

        $header = $this->decodeJwtHeader($jwt);
        $kid = isset($header->kid) ? (string) $header->kid : '';

        $jwks = $this->fetchJwks($baseUrl);
        $keys = JWK::parseKeySet($jwks);

        if ($kid !== '' && isset($keys[$kid])) {
            $key = $keys[$kid];
        } elseif (count($keys) === 1) {
            $key = reset($keys);
        } else {
            throw new RuntimeException('Unable to resolve JWKS signing key.');
        }

        $claims = JWT::decode($jwt, $key);

        $iss = isset($claims->iss) ? rtrim((string) $claims->iss, '/') : '';
        if ($iss !== $baseUrl) {
            throw new RuntimeException('Invalid token issuer.');
        }

        $aud = isset($claims->aud) ? (string) $claims->aud : '';
        $claimProjectId = isset($claims->project_id) ? (string) $claims->project_id : '';

        if ($aud !== $projectId && $claimProjectId !== $projectId) {
            throw new RuntimeException('Invalid token audience.');
        }

        $exp = isset($claims->exp) ? (int) $claims->exp : 0;
        $now = is_int(JWT::$timestamp) ? JWT::$timestamp : time();

        if ($exp <= $now) {
            throw new RuntimeException('Token has expired.');
        }

        return $claims;
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    private function fetchJwks(string $idpUrl): array
    {
        $cacheSeconds = (int) config('sso.jwks_cache_seconds', 3600);

        /** @var array{keys: list<array<string, mixed>>} $jwks */
        $jwks = Cache::remember(
            'company.sso.jwks.'.$idpUrl,
            $cacheSeconds,
            function () use ($idpUrl): array {
                $response = Http::get(OAuthUrls::jwks($idpUrl));
                $response->throw();

                /** @var array{keys: list<array<string, mixed>>} $data */
                $data = $response->json();

                return $data;
            },
        );

        return $jwks;
    }

    private function decodeJwtHeader(string $jwt): stdClass
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            throw new RuntimeException('Malformed JWT.');
        }

        $decoded = json_decode($this->base64UrlDecode($parts[0]));

        if (! $decoded instanceof stdClass) {
            throw new RuntimeException('Malformed JWT header.');
        }

        return $decoded;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Malformed JWT encoding.');
        }

        return $decoded;
    }
}
