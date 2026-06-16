<?php

namespace Company\Sso\Core\Jwt;

use RuntimeException;

final class JwtKeyLoader
{
    public function __construct(
        private readonly string $privatePem,
        private readonly string $publicPem,
    ) {}

    public static function fromApplicationConfig(): self
    {
        $priv = self::normalizedPemOrNull((string) (config('sso.private_key_pem') ?? ''));
        $pub = self::normalizedPemOrNull((string) (config('sso.public_key_pem') ?? ''));

        $privPath = (string) (config('sso.private_key_path') ?? '');
        $pubPath = (string) (config('sso.public_key_path') ?? '');

        if ($priv === null || $priv === '') {
            $priv = self::normalizedPemOrNull(self::readPath($privPath));
        }

        if ($pub === null || $pub === '') {
            $pub = self::normalizedPemOrNull(self::readPath($pubPath));
        }

        if (($priv ?? '') === '' || ($pub ?? '') === '') {
            throw new RuntimeException(
                'JWT RS256 keys are not configured. Set JWT_PRIVATE_KEY_PEM/JWT_PUBLIC_KEY_PEM or *_PATH.'
            );
        }

        return new self($priv, $pub);
    }

    /**
     * @return array{kty: string, kid: string, use: string, alg: string, n: string, e: string}
     */
    public function publicJwk(): array
    {
        $resource = openssl_pkey_get_public($this->publicPem);

        if ($resource === false) {
            throw new RuntimeException('JWT public key is invalid PEM.');
        }

        $details = openssl_pkey_get_details($resource);

        if (! is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new RuntimeException('JWT public key must be RSA.');
        }

        /** @var array{n: string, e: string} $rsa */
        $rsa = $details['rsa'];

        return [
            'kty' => 'RSA',
            'kid' => (string) config('sso.key_id'),
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => self::base64UrlIntegerFromBigEndianUnsigned($rsa['n']),
            'e' => self::base64UrlIntegerFromBigEndianUnsigned($rsa['e']),
        ];
    }

    public function getPrivateKeyPem(): string
    {
        return $this->privatePem;
    }

    public function getPublicKeyPem(): string
    {
        return $this->publicPem;
    }

    private static function readPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $resolved = self::resolvePath($path);

        if (! is_file($resolved)) {
            throw new RuntimeException(sprintf('JWT key path not found (%s).', $path));
        }

        return file_get_contents($resolved) ?: null;
    }

    private static function resolvePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/')
            || preg_match('#^[A-Za-z]:/#', $normalized) === 1) {
            return $path;
        }

        return base_path($normalized);
    }

    private static function normalizedPemOrNull(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_contains($value, '\n')
            ? str_replace('\\n', "\n", $value)
            : $value;
    }

    private static function base64UrlIntegerFromBigEndianUnsigned(string $bytes): string
    {
        $trimmed = $bytes;

        while (strlen($trimmed) > 0 && ord($trimmed[0]) === 0) {
            $trimmed = substr($trimmed, 1);
        }

        return rtrim(strtr(base64_encode($trimmed), '+/', '-_'), '=');
    }
}
