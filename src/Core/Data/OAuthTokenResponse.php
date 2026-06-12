<?php

namespace Company\Sso\Core\Data;

final readonly class OAuthTokenResponse
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public string $tokenType = 'Bearer',
    ) {}

    /**
     * @return array{access_token: string, expires_in: int, token_type: string}
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'expires_in' => $this->expiresIn,
            'token_type' => $this->tokenType,
        ];
    }
}
